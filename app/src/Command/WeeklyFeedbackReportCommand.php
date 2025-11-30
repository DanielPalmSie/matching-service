<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OpenAIService;
use App\Service\PdfGenerator;
use App\Service\ReportMailer;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:weekly-feedback-report', description: 'Generate and email weekly feedback analysis report')]
class WeeklyFeedbackReportCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly Connection $connection,
        private readonly OpenAIService $openAIService,
        private readonly PdfGenerator $pdfGenerator,
        private readonly ReportMailer $reportMailer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable('now');
        $from = $now->sub(new DateInterval('P7D'));

        $io->title('Weekly feedback report');
        $this->logger->info('Starting weekly feedback report generation.', [
            'range_start' => $from->format(DateTimeImmutable::ATOM),
            'range_end' => $now->format(DateTimeImmutable::ATOM),
        ]);

        $comments = $this->fetchRecentComments($from, $now);
        $totalComments = count($comments);
        $io->text(sprintf('Collected %d comments from the last 7 days.', $totalComments));

        $clusters = $this->clusterComments($comments, $io);
        $reasonCodes = $this->fetchGroupedCounts('reason_code', $from, $now);
        $mainIssues = $this->fetchGroupedCounts('main_issue', $from, $now);
        $averageRelevance = $this->fetchAverageRelevance($from, $now);

        $data = [
            'range' => [
                'start' => $from->format('Y-m-d'),
                'end' => $now->format('Y-m-d'),
            ],
            'totalComments' => $totalComments,
            'clusters' => $this->recalculatePercentages($clusters, max($totalComments, 1)),
            'reasonCodes' => $reasonCodes,
            'mainIssues' => $mainIssues,
            'averageRelevance' => $averageRelevance,
        ];

        $io->text('Rendering PDF report...');
        $pdfContent = $this->pdfGenerator->generateWeeklyReport($data);

        $io->text('Sending report via email...');
        $this->reportMailer->sendReport($pdfContent);

        $this->logger->info('Weekly feedback report sent successfully.', ['total_comments' => $totalComments]);
        $io->success('Weekly feedback report has been sent.');

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function fetchRecentComments(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $sql = 'SELECT comment FROM match_feedback WHERE created_at >= :from AND created_at < :to AND comment IS NOT NULL AND comment <> '''' ORDER BY created_at ASC';
        $comments = $this->connection->fetchFirstColumn($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ], [
            'from' => Types::DATETIME_MUTABLE,
            'to' => Types::DATETIME_MUTABLE,
        ]);

        return array_map('strval', $comments);
    }

    /**
     * @param array<int, string> $comments
     *
     * @return array<string, array<string, mixed>>
     */
    private function clusterComments(array $comments, SymfonyStyle $io): array
    {
        if ($comments === []) {
            $io->warning('No comments to process for the selected period.');

            return [];
        }

        $batchSize = min(200, max(50, self::DEFAULT_BATCH_SIZE));
        $batches = array_chunk($comments, $batchSize);
        $aggregated = [];

        foreach ($batches as $index => $batch) {
            $io->text(sprintf('Processing batch %d/%d with %d comments...', $index + 1, count($batches), count($batch)));
            $this->logger->info('Sending feedback batch to OpenAI.', ['batch' => $index + 1, 'size' => count($batch)]);

            $clusters = $this->openAIService->clusterFeedback($batch);
            foreach ($clusters as $cluster) {
                $label = $cluster['label'] ?? 'Unlabeled';
                $count = isset($cluster['count']) ? (int) $cluster['count'] : count($cluster['examples'] ?? []);
                $description = $cluster['description'] ?? '';
                $examples = $cluster['examples'] ?? [];

                if (!isset($aggregated[$label])) {
                    $aggregated[$label] = [
                        'label' => $label,
                        'description' => $description,
                        'count' => 0,
                        'examples' => [],
                    ];
                }

                $aggregated[$label]['count'] += $count;
                $aggregated[$label]['description'] = $description ?: $aggregated[$label]['description'];
                $aggregated[$label]['examples'] = array_slice(array_unique(array_merge($aggregated[$label]['examples'], $examples)), 0, 5);
            }
        }

        uasort($aggregated, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $aggregated;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function fetchGroupedCounts(string $column, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if (!$this->tableHasColumn('match_feedback', $column)) {
            $this->logger->warning('Requested column missing for aggregation.', ['column' => $column]);

            return [];
        }

        $sql = sprintf(
            'SELECT %s AS label, COUNT(*) AS total FROM match_feedback WHERE created_at >= :from AND created_at < :to GROUP BY %s ORDER BY total DESC',
            $column,
            $column
        );

        return $this->connection->fetchAllAssociative($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ], [
            'from' => Types::DATETIME_MUTABLE,
            'to' => Types::DATETIME_MUTABLE,
        ]);
    }

    private function fetchAverageRelevance(DateTimeImmutable $from, DateTimeImmutable $to): float
    {
        $sql = 'SELECT AVG(relevance_score) FROM match_feedback WHERE created_at >= :from AND created_at < :to';
        $average = $this->connection->fetchOne($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ], [
            'from' => Types::DATETIME_MUTABLE,
            'to' => Types::DATETIME_MUTABLE,
        ]);

        return $average !== null ? (float) $average : 0.0;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns($table);

        return array_key_exists($column, $columns);
    }

    /**
     * @param array<string, array<string, mixed>> $clusters
     *
     * @return array<int, array<string, mixed>>
     */
    private function recalculatePercentages(array $clusters, int $totalComments): array
    {
        $normalized = [];
        foreach ($clusters as $cluster) {
            $percentage = $totalComments > 0 ? round(($cluster['count'] / $totalComments) * 100, 2) : 0.0;
            $cluster['percentage'] = $percentage;
            $normalized[] = $cluster;
        }

        return $normalized;
    }
}

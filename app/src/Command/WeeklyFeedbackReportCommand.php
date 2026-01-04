<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OpenAIService;
use App\Service\PdfGenerator;
use App\Service\ReportMailer;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsCommand(name: 'app:weekly-feedback-report', description: 'Generate and email weekly feedback analysis report')]
class WeeklyFeedbackReportCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 100;
    private const REPORT_NAME = 'weekly_feedback';
    private const LOCK_NAME = 'weekly_feedback_report';
    private const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private readonly Connection $connection,
        private readonly OpenAIService $openAIService,
        private readonly PdfGenerator $pdfGenerator,
        private readonly ReportMailer $reportMailer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('week', null, InputOption::VALUE_REQUIRED, 'ISO week (YYYY-Www)')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Custom range start (YYYY-MM-DD or ISO-8601)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Custom range end (YYYY-MM-DD or ISO-8601)')
            ->addOption('recipient', null, InputOption::VALUE_REQUIRED, 'Override report recipient email')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Generate report without sending email')
            ->addOption('no-openai', null, InputOption::VALUE_NONE, 'Skip OpenAI clustering');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timezone = new DateTimeZone(self::TIMEZONE);
        $now = new DateTimeImmutable('now', $timezone);
        $runId = $this->generateRunId();
        $startTime = microtime(true);

        $io->title('Weekly feedback report');
        [$windowStart, $windowEnd] = $this->resolveWindow($input, $io, $timezone, $now);
        if ($windowStart === null || $windowEnd === null) {
            return Command::FAILURE;
        }

        $recipient = $this->reportMailer->resolveRecipient($input->getOption('recipient'));
        $reportHash = hash('sha256', sprintf(
            '%s|%s|%s|%s',
            self::REPORT_NAME,
            $windowStart->format(DateTimeImmutable::ATOM),
            $windowEnd->format(DateTimeImmutable::ATOM),
            $recipient
        ));

        $this->logger->info('Starting weekly feedback report generation.', [
            'run_id' => $runId,
            'window_start' => $windowStart->format(DateTimeImmutable::ATOM),
            'window_end' => $windowEnd->format(DateTimeImmutable::ATOM),
            'recipient' => $recipient,
        ]);

        $lockAcquired = $this->acquireLock($runId);
        if (!$lockAcquired) {
            $this->logger->warning('Weekly feedback report lock is already held, skipping run.', [
                'run_id' => $runId,
                'window_start' => $windowStart->format(DateTimeImmutable::ATOM),
                'window_end' => $windowEnd->format(DateTimeImmutable::ATOM),
                'recipient' => $recipient,
            ]);
            $io->warning('Another report run is in progress. Skipping.');

            return Command::SUCCESS;
        }

        try {
            if ($this->hasSuccessfulRun($reportHash)) {
                $this->logger->info('Weekly feedback report already sent for this window.', [
                    'run_id' => $runId,
                    'window_start' => $windowStart->format(DateTimeImmutable::ATOM),
                    'window_end' => $windowEnd->format(DateTimeImmutable::ATOM),
                    'recipient' => $recipient,
                ]);
                $io->success('Weekly feedback report already sent for this window.');

                return Command::SUCCESS;
            }

            $comments = $this->fetchRecentComments($windowStart, $windowEnd);
            $totalComments = count($comments);
            $io->text(sprintf('Collected %d comments from the selected window.', $totalComments));

            $clusters = [];
            if ($input->getOption('no-openai')) {
                $io->warning('OpenAI clustering disabled by --no-openai.');
            } else {
                $clusters = $this->clusterComments($comments, $io);
            }

            $reasonCodes = $this->fetchGroupedCounts('reason_code', $windowStart, $windowEnd);
            $mainIssues = $this->fetchGroupedCounts('main_issue', $windowStart, $windowEnd);
            $averageRelevance = $this->fetchAverageRelevance($windowStart, $windowEnd);

            $data = [
                'range' => [
                    'start' => $windowStart->format('Y-m-d'),
                    'end' => $windowEnd->sub(new DateInterval('PT1S'))->format('Y-m-d'),
                ],
                'totalComments' => $totalComments,
                'clusters' => $this->recalculatePercentages($clusters, max($totalComments, 1)),
                'reasonCodes' => $reasonCodes,
                'mainIssues' => $mainIssues,
                'averageRelevance' => $averageRelevance,
            ];

            $io->text('Rendering PDF report...');
            $pdfContent = $this->pdfGenerator->generateWeeklyReport($data);
            $pdfSize = strlen($pdfContent);
            $clustersCount = count($clusters);

            if ($input->getOption('dry-run')) {
                $this->logger->info('Weekly feedback report dry run completed.', [
                    'run_id' => $runId,
                    'window_start' => $windowStart->format(DateTimeImmutable::ATOM),
                    'window_end' => $windowEnd->format(DateTimeImmutable::ATOM),
                    'recipient' => $recipient,
                    'total_comments' => $totalComments,
                    'clusters_count' => $clustersCount,
                    'duration_ms' => $this->toDurationMs($startTime),
                    'pdf_size_bytes' => $pdfSize,
                ]);
                $io->success('Weekly feedback report dry run completed.');

                return Command::SUCCESS;
            }

            $io->text('Sending report via email...');
            try {
                $this->reportMailer->sendReport($pdfContent, $recipient);
            } catch (TransportExceptionInterface $exception) {
                $this->persistRun(
                    $windowStart,
                    $windowEnd,
                    $recipient,
                    $reportHash,
                    'FAILED',
                    null,
                    [
                        'total_comments' => $totalComments,
                        'clusters_count' => $clustersCount,
                        'duration_ms' => $this->toDurationMs($startTime),
                        'pdf_size_bytes' => $pdfSize,
                        'error' => $exception->getMessage(),
                    ]
                );
                $this->logger->error('Weekly feedback report failed to send.', [
                    'run_id' => $runId,
                    'window_start' => $windowStart->format(DateTimeImmutable::ATOM),
                    'window_end' => $windowEnd->format(DateTimeImmutable::ATOM),
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
                $io->error('Weekly feedback report failed to send.');

                return Command::FAILURE;
            }

            $sentAt = new DateTimeImmutable('now', $timezone);
            $this->persistRun(
                $windowStart,
                $windowEnd,
                $recipient,
                $reportHash,
                'SUCCESS',
                $sentAt,
                [
                    'total_comments' => $totalComments,
                    'clusters_count' => $clustersCount,
                    'duration_ms' => $this->toDurationMs($startTime),
                    'pdf_size_bytes' => $pdfSize,
                ]
            );

            $this->logger->info('Weekly feedback report sent successfully.', [
                'run_id' => $runId,
                'window_start' => $windowStart->format(DateTimeImmutable::ATOM),
                'window_end' => $windowEnd->format(DateTimeImmutable::ATOM),
                'recipient' => $recipient,
                'total_comments' => $totalComments,
                'clusters_count' => $clustersCount,
                'duration_ms' => $this->toDurationMs($startTime),
                'pdf_size_bytes' => $pdfSize,
            ]);
            $io->success('Weekly feedback report has been sent.');

            return Command::SUCCESS;
        } finally {
            $this->releaseLock($runId);
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchRecentComments(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $sql = "SELECT comment
            FROM match_feedback
            WHERE created_at >= :from
              AND created_at < :to
              AND comment IS NOT NULL
              AND comment <> ''
            ORDER BY created_at ASC";

        $comments = $this->connection->fetchFirstColumn($sql, [
            'from' => $from,
            'to'   => $to,
        ], [
            'from' => Types::DATETIME_IMMUTABLE,
            'to'   => Types::DATETIME_IMMUTABLE,
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
            'SELECT %s AS label, COUNT(*) AS total
         FROM match_feedback
         WHERE created_at >= :from AND created_at < :to
         GROUP BY %s
         ORDER BY total DESC',
            $column,
            $column
        );

        return $this->connection->fetchAllAssociative($sql, [
            'from' => $from,
            'to'   => $to,
        ], [
            'from' => Types::DATETIME_IMMUTABLE,
            'to'   => Types::DATETIME_IMMUTABLE,
        ]);
    }

    private function fetchAverageRelevance(DateTimeImmutable $from, DateTimeImmutable $to): float
    {
        $sql = 'SELECT AVG(relevance_score)
            FROM match_feedback
            WHERE created_at >= :from AND created_at < :to';

        $average = $this->connection->fetchOne($sql, [
            'from' => $from,
            'to'   => $to,
        ], [
            'from' => Types::DATETIME_IMMUTABLE,
            'to'   => Types::DATETIME_IMMUTABLE,
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

    /**
     * @return array{0: DateTimeImmutable|null, 1: DateTimeImmutable|null}
     */
    private function resolveWindow(
        InputInterface $input,
        SymfonyStyle $io,
        DateTimeZone $timezone,
        DateTimeImmutable $now
    ): array {
        $week = $input->getOption('week');
        $fromOption = $input->getOption('from');
        $toOption = $input->getOption('to');

        if ($week && ($fromOption || $toOption)) {
            $io->error('Use either --week or --from/--to, not both.');

            return [null, null];
        }

        if ($week) {
            $windowStart = $this->parseWeekStart($week, $io, $timezone);
            if ($windowStart === null) {
                return [null, null];
            }

            return [$windowStart, $windowStart->modify('+1 week')];
        }

        if ($fromOption || $toOption) {
            if (!$fromOption || !$toOption) {
                $io->error('Both --from and --to must be provided together.');

                return [null, null];
            }

            $from = $this->parseDate($fromOption, $io, $timezone, 'from');
            $to = $this->parseDate($toOption, $io, $timezone, 'to');
            if ($from === null || $to === null) {
                return [null, null];
            }

            if ($from >= $to) {
                $io->error('--from must be earlier than --to.');

                return [null, null];
            }

            return [$from, $to];
        }

        $startOfWeek = $now->modify('monday this week')->setTime(0, 0, 0);
        $windowStart = $startOfWeek->sub(new DateInterval('P7D'));
        $windowEnd = $startOfWeek;

        return [$windowStart, $windowEnd];
    }

    private function parseWeekStart(string $week, SymfonyStyle $io, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (!preg_match('/^(?<year>\d{4})-W(?<week>\d{2})$/', $week, $matches)) {
            $io->error('Invalid --week format. Expected YYYY-Www.');

            return null;
        }

        $year = (int) $matches['year'];
        $weekNumber = (int) $matches['week'];
        $date = (new DateTimeImmutable('now', $timezone))->setISODate($year, $weekNumber)->setTime(0, 0, 0);

        return $date;
    }

    private function parseDate(string $value, SymfonyStyle $io, DateTimeZone $timezone, string $label): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (\Throwable $exception) {
            $io->error(sprintf('Invalid --%s value: %s', $label, $value));

            return null;
        }
    }

    private function generateRunId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function acquireLock(string $runId): bool
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'postgresql') {
            $lockKey = (int) sprintf('%u', crc32(self::LOCK_NAME));
            $result = $this->connection->fetchOne('SELECT pg_try_advisory_lock(:key)', ['key' => $lockKey]);

            return (bool) $result;
        }

        if ($platform === 'mysql') {
            $result = $this->connection->fetchOne('SELECT GET_LOCK(:key, 0)', ['key' => self::LOCK_NAME]);

            return (int) $result === 1;
        }

        $this->logger->warning('No supported advisory lock for this database platform.', [
            'run_id' => $runId,
            'platform' => $platform,
        ]);

        return true;
    }

    private function releaseLock(string $runId): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'postgresql') {
            $lockKey = (int) sprintf('%u', crc32(self::LOCK_NAME));
            $this->connection->executeStatement('SELECT pg_advisory_unlock(:key)', ['key' => $lockKey]);

            return;
        }

        if ($platform === 'mysql') {
            $this->connection->executeStatement('SELECT RELEASE_LOCK(:key)', ['key' => self::LOCK_NAME]);

            return;
        }

        $this->logger->warning('Skipping lock release for unsupported database platform.', [
            'run_id' => $runId,
            'platform' => $platform,
        ]);
    }

    private function hasSuccessfulRun(string $hash): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT 1 FROM report_run WHERE hash = :hash AND status = :status LIMIT 1',
            ['hash' => $hash, 'status' => 'SUCCESS']
        );

        return (bool) $result;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function persistRun(
        DateTimeImmutable $windowStart,
        DateTimeImmutable $windowEnd,
        string $recipient,
        string $hash,
        string $status,
        ?DateTimeImmutable $sentAt,
        array $meta
    ): void {
        $existingId = $this->connection->fetchOne('SELECT id FROM report_run WHERE hash = :hash', ['hash' => $hash]);
        $data = [
            'report_name' => self::REPORT_NAME,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'recipient' => $recipient,
            'status' => $status,
            'sent_at' => $sentAt,
            'hash' => $hash,
            'meta' => $meta,
            'created_at' => new DateTimeImmutable('now', $windowStart->getTimezone()),
        ];
        $updateData = [
            'status' => $status,
            'sent_at' => $sentAt,
            'meta' => $meta,
        ];
        $insertTypes = [
            'window_start' => Types::DATETIME_IMMUTABLE,
            'window_end' => Types::DATETIME_IMMUTABLE,
            'sent_at' => Types::DATETIME_IMMUTABLE,
            'meta' => Types::JSON,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ];
        $updateTypes = [
            'sent_at' => Types::DATETIME_IMMUTABLE,
            'meta' => Types::JSON,
        ];

        if ($existingId) {
            $this->connection->update('report_run', $updateData, ['id' => $existingId], $updateTypes);

            return;
        }

        $this->connection->insert('report_run', $data, $insertTypes);
    }

    private function toDurationMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }
}

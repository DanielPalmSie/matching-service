<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RequestRepository;
use App\Service\Embedding\EmbeddingClientInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:embeddings:backfill-requests',
    description: 'Backfill request embeddings that are missing or not ready.',
)]
final class BackfillRequestEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly RequestRepository $requestRepository,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(OPENAI_EMBEDDING_MODEL)%')]
        private readonly string $embeddingModel,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of requests to process.')
            ->addOption('from-id', null, InputOption::VALUE_OPTIONAL, 'Start from request id (inclusive).')
            ->addOption('to-id', null, InputOption::VALUE_OPTIONAL, 'Stop at request id (inclusive).')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Batch size for processing.', 200)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not persist any changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = $this->normalizeOptionalInt($input->getOption('limit'));
        $fromId = $this->normalizeOptionalInt($input->getOption('from-id'));
        $toId = $this->normalizeOptionalInt($input->getOption('to-id'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        if ($fromId !== null && $toId !== null && $fromId > $toId) {
            $io->error('from-id must be less than or equal to to-id.');

            return Command::INVALID;
        }

        $cursor = $fromId !== null ? $fromId - 1 : null;
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        while (true) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }

            $remaining = $limit !== null ? max(0, $limit - $processed) : null;
            $batchLimit = $remaining !== null ? min($batchSize, $remaining) : $batchSize;
            if ($batchLimit < 1) {
                break;
            }

            $requests = $this->requestRepository->findEmbeddingBackfillBatch($batchLimit, $cursor, $toId);
            if ($requests === []) {
                break;
            }

            foreach ($requests as $request) {
                $processed++;
                $requestId = $request->getId();
                if ($requestId !== null) {
                    $cursor = $requestId;
                }

                if (trim($request->getRawText()) === '') {
                    $skipped++;
                    continue;
                }

                try {
                    /** @var list<float> $embedding */
                    $embedding = $this->embeddingClient->embed($request->getRawText());
                    if (!$dryRun) {
                        $request->setEmbedding($embedding);
                        $request->setEmbeddingStatus('ready');
                        $request->setEmbeddingModel($this->embeddingModel);
                        $request->setEmbeddingUpdatedAt(new DateTimeImmutable());
                        $request->setEmbeddingError(null);
                    }
                    $updated++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->logger->error('Failed to backfill request embedding.', [
                        'requestId' => $requestId,
                        'exception' => $exception,
                    ]);

                    if (!$dryRun) {
                        $request->setEmbeddingStatus('pending');
                        $request->setEmbeddingModel($this->embeddingModel);
                        $request->setEmbeddingUpdatedAt(new DateTimeImmutable());
                        $request->setEmbeddingError($exception->getMessage());
                    }
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $summary = sprintf(
            'Processed %d requests: %d updated, %d skipped, %d failed.',
            $processed,
            $updated,
            $skipped,
            $failed,
        );

        if ($failed > 0) {
            $io->warning($summary);

            return Command::FAILURE;
        }

        $io->success($summary);

        return Command::SUCCESS;
    }

    private function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = (int) $value;
        if ($normalized < 1) {
            return null;
        }

        return $normalized;
    }
}

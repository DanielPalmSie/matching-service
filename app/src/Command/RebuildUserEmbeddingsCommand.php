<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RequestRepository;
use App\Repository\UserRepository;
use App\Service\UserEmbeddingSynchronizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:embeddings:rebuild-users',
    description: 'Re-embed all user profiles with the configured embedding model.',
)]
final class RebuildUserEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RequestRepository $requestRepository,
        private readonly UserEmbeddingSynchronizer $userEmbeddingSynchronizer,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();
        $total = count($users);
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $latestRequest = $this->requestRepository->findLatestActiveByOwner($user);
                $sourceText = $latestRequest?->getRawText();

                if ($this->userEmbeddingSynchronizer->sync($user, $sourceText)) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $this->logger->error('Failed to rebuild embedding for user.', [
                    'userId' => $user->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        if ($failed > 0) {
            $io->warning(sprintf('Processed %d users: %d updated, %d skipped, %d failed.', $total, $updated, $skipped, $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('Successfully rebuilt embeddings for %d users (%d skipped with no available text).', $updated, $skipped));

        return Command::SUCCESS;
    }
}

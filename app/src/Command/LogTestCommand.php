<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:log-test')]
class LogTestCommand extends Command
{
    public function __construct(private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('ELK APP INDEX TEST 5', [
            'range_start' => (new \DateTimeImmutable('-1 day'))->format(\DateTimeImmutable::ATOM),
            'range_end' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
        ]);

        $output->writeln('logged');
        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Queue\Failed\FailedJobStoreInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class QueueFailedCommand extends Command
{
    public function __construct(
        private readonly FailedJobStoreInterface $failedJobStore,
    ) {
        parent::__construct('queue:failed');
    }

    protected function configure(): void
    {
        $this->setDescription('List all failed queue jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Failed Jobs');
        $style->newLine();

        $failedJobs = $this->failedJobStore->all();

        if ($failedJobs === []) {
            $style->info('No failed jobs found');
            $style->newLine();

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($failedJobs as $job) {
            $rows[] = [
                $job->id,
                $job->queue,
                mb_substr($job->payload, 0, 50) . (mb_strlen($job->payload) > 50 ? '...' : ''),
                $job->failedAt->format('Y-m-d H:i:s'),
                mb_substr($job->exception, 0, 60) . (mb_strlen($job->exception) > 60 ? '...' : ''),
            ];
        }

        $style->table(
            ['ID', 'Queue', 'Payload', 'Failed At', 'Exception'],
            $rows,
        );

        $style->newLine();
        $style->info(count($failedJobs) . ' failed job(s) found');
        $style->newLine();

        return Command::SUCCESS;
    }
}

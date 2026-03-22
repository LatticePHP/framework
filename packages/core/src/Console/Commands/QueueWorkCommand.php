<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Queue\Worker;
use Lattice\Queue\WorkerOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class QueueWorkCommand extends Command
{
    public function __construct(
        private readonly Worker $worker,
    ) {
        parent::__construct('queue:work');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Process jobs from the queue')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The queue to process', 'default')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The queue connection', 'default')
            ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'Maximum number of jobs to process (0 = unlimited)', '0')
            ->addOption('max-time', null, InputOption::VALUE_OPTIONAL, 'Maximum seconds to run (0 = unlimited)', '0')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Seconds to sleep when no jobs available', '3')
            ->addOption('memory', null, InputOption::VALUE_OPTIONAL, 'Memory limit in megabytes', '128');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Queue Worker');

        $queue = (string) $input->getOption('queue');
        $connection = (string) $input->getOption('connection');
        $maxJobs = (int) $input->getOption('max-jobs');
        $maxTime = (int) $input->getOption('max-time');
        $sleep = (int) $input->getOption('sleep');
        $memoryLimit = (int) $input->getOption('memory');

        $style->keyValue('Queue', $queue);
        $style->keyValue('Connection', $connection);
        $style->keyValue('Memory limit', $memoryLimit . ' MB');

        if ($maxJobs > 0) {
            $style->keyValue('Max jobs', (string) $maxJobs);
        }

        if ($maxTime > 0) {
            $style->keyValue('Max time', $maxTime . 's');
        }

        $style->newLine();
        $style->info('Processing jobs...');
        $style->newLine();

        $options = new WorkerOptions(
            queue: $queue,
            connection: $connection,
            maxJobs: $maxJobs,
            maxTime: $maxTime,
            sleep: $sleep,
            memoryLimit: $memoryLimit,
        );

        $this->worker->work($options);

        $style->success('Worker stopped');
        $style->newLine();

        return Command::SUCCESS;
    }
}

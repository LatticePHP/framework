<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Queue\Driver\QueueDriverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class QueueMonitorCommand extends Command
{
    /** @var list<string> */
    private array $queues;

    /**
     * @param list<string> $queues
     */
    public function __construct(
        private readonly QueueDriverInterface $driver,
        array $queues = ['default'],
    ) {
        $this->queues = $queues;
        parent::__construct('queue:monitor');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Monitor queue sizes and status')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Specific queue to monitor')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    /**
     * @return list<string>
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueFilter = $input->getOption('queue');
        $queuesToMonitor = $this->queues;

        if (is_string($queueFilter) && $queueFilter !== '') {
            $queuesToMonitor = [$queueFilter];
        }

        $queueData = [];
        $totalSize = 0;

        foreach ($queuesToMonitor as $queue) {
            $size = $this->driver->size($queue);
            $totalSize += $size;

            $queueData[] = [
                'name' => $queue,
                'size' => $size,
                'status' => $size > 0 ? 'active' : 'idle',
            ];
        }

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'queues' => $queueData,
                'total' => $totalSize,
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Queue Monitor');
        $style->newLine();

        if ($queueData === []) {
            $style->info('No queues configured');
            $style->newLine();

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($queueData as $data) {
            $statusColor = $data['status'] === 'active' ? 'green' : 'gray';

            $rows[] = [
                "<fg=cyan>{$data['name']}</>",
                (string) $data['size'],
                "<fg={$statusColor}>{$data['status']}</>",
            ];
        }

        $style->table(
            ['Queue', 'Size', 'Status'],
            $rows,
        );

        $style->newLine();
        $style->info('Total pending jobs: ' . $totalSize);
        $style->newLine();

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Scheduler\Schedule;
use Lattice\Scheduler\ScheduleRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ScheduleRunCommand extends Command
{
    public function __construct(
        private readonly ScheduleRunner $runner,
        private readonly Schedule $schedule,
    ) {
        parent::__construct('schedule:run');
    }

    protected function configure(): void
    {
        $this->setDescription('Run all due scheduled tasks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Schedule Runner');
        $style->newLine();

        $now = new \DateTimeImmutable();
        $style->keyValue('Time', $now->format('Y-m-d H:i:s'));
        $style->newLine();

        $results = $this->runner->run($this->schedule, $now);

        if ($results === []) {
            $style->info('No scheduled tasks are due');
            $style->newLine();

            return Command::SUCCESS;
        }

        foreach ($results as $result) {
            if ($result['success']) {
                $style->success("Ran: {$result['name']}");
            } else {
                $style->error("Failed: {$result['name']} - {$result['error']}");
            }
        }

        $style->newLine();

        $successful = count(array_filter($results, fn (array $r): bool => $r['success']));
        $failed = count($results) - $successful;

        $style->info("{$successful} task(s) completed, {$failed} failed");
        $style->newLine();

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

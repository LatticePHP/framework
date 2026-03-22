<?php

declare(strict_types=1);

namespace Lattice\Anvil\Console;

use Lattice\Anvil\Detection\DetectionResult;
use Lattice\Anvil\Detection\SystemDetector;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AnvilStatusCommand extends Command
{
    private ?SystemDetector $detector = null;

    public function __construct(?SystemDetector $detector = null)
    {
        $this->detector = $detector;
        parent::__construct('anvil:status');
    }

    protected function configure(): void
    {
        $this->setDescription('Show the status of all detected system services');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Anvil — System Status');
        $style->newLine();

        $detector = $this->detector ?? new SystemDetector();
        $results = $detector->detectAll();

        $rows = array_map(fn(DetectionResult $r): array => [
            $r->name,
            $r->installed ? '<fg=green>Yes</>' : '<fg=red>No</>',
            $r->version ?? '-',
            $this->formatStatus($r),
        ], $results);

        $style->table(
            ['Service', 'Installed', 'Version', 'Status'],
            $rows,
        );

        $installed = count(array_filter($results, fn(DetectionResult $r): bool => $r->installed));
        $total = count($results);

        $style->newLine();
        $style->info("{$installed}/{$total} services detected");
        $style->newLine();

        return Command::SUCCESS;
    }

    private function formatStatus(DetectionResult $result): string
    {
        if (!$result->installed) {
            return '<fg=gray>Not installed</>';
        }

        return match ($result->status) {
            'running' => '<fg=green>Running</>',
            'stopped' => '<fg=red>Stopped</>',
            'installed' => '<fg=yellow>Installed</>',
            default => '<fg=gray>Unknown</>',
        };
    }
}

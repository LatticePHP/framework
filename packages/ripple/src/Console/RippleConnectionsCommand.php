<?php

declare(strict_types=1);

namespace Lattice\Ripple\Console;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists connected WebSocket clients.
 *
 * Usage: php lattice ripple:connections [--json] [--count]
 */
final class RippleConnectionsCommand extends Command
{
    public function __construct()
    {
        parent::__construct('ripple:connections');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List connected WebSocket clients')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('count', null, InputOption::VALUE_NONE, 'Only show the connection count');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);

        $isJson = (bool) $input->getOption('json');
        $isCount = (bool) $input->getOption('count');

        // In a real implementation, this would connect to the running
        // Ripple server via an admin socket or Redis metadata keys.
        // For now, display a placeholder message.
        if ($isCount) {
            if ($isJson) {
                $output->writeln(json_encode(['count' => 0], JSON_THROW_ON_ERROR));
            } else {
                $style->info('Active connections: <fg=white>0</>');
            }

            return Command::SUCCESS;
        }

        if ($isJson) {
            $output->writeln(json_encode(['connections' => []], JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $style->banner();
        $style->header('Connected Clients');
        $style->newLine();
        $style->info('No active connections.');

        return Command::SUCCESS;
    }
}

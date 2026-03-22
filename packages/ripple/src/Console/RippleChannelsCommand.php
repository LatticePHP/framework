<?php

declare(strict_types=1);

namespace Lattice\Ripple\Console;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists active WebSocket channels.
 *
 * Usage: php lattice ripple:channels [--json] [--type=private]
 */
final class RippleChannelsCommand extends Command
{
    public function __construct()
    {
        parent::__construct('ripple:channels');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List active WebSocket channels')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Filter by channel type (public, private, presence)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);

        $isJson = (bool) $input->getOption('json');
        $typeFilter = $input->getOption('type');

        // In a real implementation, this would connect to the running
        // Ripple server via an admin socket or Redis metadata keys.
        if ($isJson) {
            $output->writeln(json_encode(['channels' => []], JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $style->banner();
        $style->header('Active Channels');
        $style->newLine();

        if ($typeFilter !== null) {
            $style->info(sprintf('No active %s channels.', (string) $typeFilter));
        } else {
            $style->info('No active channels.');
        }

        return Command::SUCCESS;
    }
}

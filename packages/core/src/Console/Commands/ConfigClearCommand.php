<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigClearCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct('config:clear');
    }

    protected function configure(): void
    {
        $this->setDescription('Remove the configuration cache file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Config Cache');
        $style->newLine();

        $cachePath = $this->basePath . '/bootstrap/cache/config.php';

        if (!file_exists($cachePath)) {
            $style->info('Configuration cache file does not exist');
            $style->newLine();

            return Command::SUCCESS;
        }

        $result = unlink($cachePath);

        if (!$result) {
            $style->error('Failed to remove configuration cache file');
            $style->newLine();

            return Command::FAILURE;
        }

        $style->success('Configuration cache cleared successfully');
        $style->newLine();

        return Command::SUCCESS;
    }
}

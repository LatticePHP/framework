<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ServeCommand extends Command
{
    public function __construct()
    {
        parent::__construct('serve');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start the development server')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve on', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve on', '8080');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = (string) $input->getOption('host');
        $port = (string) $input->getOption('port');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->info("Starting server at <fg=white>http://{$host}:{$port}</>");
        $style->info("Press <fg=yellow>Ctrl+C</> to stop");
        $style->newLine();

        $publicDir = getcwd() . '/public';

        if (!is_dir($publicDir)) {
            $style->warning("Public directory not found at {$publicDir}, using current directory");
            $publicDir = (string) getcwd();
        }

        passthru("php -S {$host}:{$port} -t " . escapeshellarg($publicDir));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Config\ConfigRepository;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigCacheCommand extends Command
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly string $basePath,
    ) {
        parent::__construct('config:cache');
    }

    protected function configure(): void
    {
        $this->setDescription('Create a configuration cache file for faster configuration loading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Config Cache');
        $style->newLine();

        $config = $this->config->all();

        $cacheDir = $this->basePath . '/bootstrap/cache';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cachePath = $cacheDir . '/config.php';
        $content = '<?php return ' . var_export($config, true) . ';' . "\n";

        $result = file_put_contents($cachePath, $content);

        if ($result === false) {
            $style->error('Failed to write config cache file');
            $style->newLine();

            return Command::FAILURE;
        }

        $style->success('Configuration cached successfully');
        $style->keyValue('Path', $cachePath);
        $style->keyValue('Keys', (string) count($config));
        $style->newLine();

        return Command::SUCCESS;
    }
}

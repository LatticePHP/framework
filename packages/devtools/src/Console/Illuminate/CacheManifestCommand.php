<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Illuminate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Caches the compiled module manifest for production.
 *
 * Usage: lattice cache:manifest
 */
final class CacheManifestCommand extends LatticeCommand
{
    private string $cachePath;
    private ?\Closure $manifestBuilder;

    public function __construct(string $cachePath = 'storage/cache', ?\Closure $manifestBuilder = null)
    {
        parent::__construct('cache:manifest');
        $this->cachePath = $cachePath;
        $this->manifestBuilder = $manifestBuilder;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Cache the compiled module manifest')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear the cached manifest instead');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $manifestFile = $this->cachePath . '/manifest.php';

        if ($input->getOption('clear')) {
            if (file_exists($manifestFile)) {
                unlink($manifestFile);
                $output->writeln('<info>Manifest cache cleared.</info>');
            } else {
                $output->writeln('<comment>No cached manifest found.</comment>');
            }
            return self::SUCCESS;
        }

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        if ($this->manifestBuilder) {
            $manifest = ($this->manifestBuilder)();
        } else {
            $manifest = [
                'generated_at' => date('Y-m-d H:i:s'),
                'modules' => [],
            ];
        }

        file_put_contents(
            $manifestFile,
            '<?php return ' . var_export($manifest, true) . ';' . PHP_EOL,
        );

        $output->writeln('<info>Manifest cached successfully.</info>');

        return self::SUCCESS;
    }
}

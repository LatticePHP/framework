<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Console;

use Lattice\Catalyst\Guidelines\GuidelineGenerator;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CatalystUpdateCommand extends Command
{
    private string $basePath;
    private ?GuidelineGenerator $generator;

    public function __construct(string $basePath = '', ?GuidelineGenerator $generator = null)
    {
        parent::__construct('catalyst:update');
        $this->basePath = $basePath;
        $this->generator = $generator;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function setGenerator(GuidelineGenerator $generator): void
    {
        $this->generator = $generator;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Refresh guidelines and CLAUDE.md when packages change')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Catalyst Update');
        $style->newLine();

        $basePath = $this->basePath !== '' ? $this->basePath : (string) getcwd();
        $generator = $this->generator ?? new GuidelineGenerator();

        // Detect packages
        $style->info('Detecting installed packages...');
        $packages = $generator->detectPackages($basePath);
        $style->success('Found ' . count($packages) . ' lattice package(s)');

        // Load guidelines
        $style->info('Loading guidelines...');
        $generator->loadBuiltinGuidelines();
        $generator->loadCustomGuidelines($basePath);
        $style->success('Loaded ' . $generator->getRegistry()->count() . ' guideline(s)');

        // Regenerate CLAUDE.md
        $claudeMdPath = $basePath . '/CLAUDE.md';
        $claudeMdContent = $generator->generateClaudeMd($packages, $basePath);
        file_put_contents($claudeMdPath, $claudeMdContent);
        $style->success('Updated CLAUDE.md');

        // Regenerate .mcp.json
        $mcpJsonPath = $basePath . '/.mcp.json';
        $mcpJsonContent = $generator->generateMcpJson($basePath);
        file_put_contents($mcpJsonPath, $mcpJsonContent);
        $style->success('Updated .mcp.json');

        $style->newLine();
        $style->success('Catalyst update complete!');
        $style->newLine();

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Console;

use Lattice\Catalyst\Guidelines\GuidelineGenerator;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CatalystInstallCommand extends Command
{
    private string $basePath;
    private ?GuidelineGenerator $generator;

    public function __construct(string $basePath = '', ?GuidelineGenerator $generator = null)
    {
        parent::__construct('catalyst:install');
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
            ->setDescription('Generate CLAUDE.md, .mcp.json, guidelines, and skills for AI agent integration')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files without prompting')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview what would be generated without writing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Catalyst Install');
        $style->newLine();

        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $basePath = $this->basePath !== '' ? $this->basePath : (string) getcwd();
        $generator = $this->generator ?? new GuidelineGenerator();

        // Step 1: Detect packages
        $style->info('Detecting installed packages...');
        $packages = $generator->detectPackages($basePath);
        $style->success('Found ' . count($packages) . ' lattice package(s)');

        // Step 2: Load guidelines
        $style->info('Loading guidelines...');
        $generator->loadBuiltinGuidelines();
        $generator->loadCustomGuidelines($basePath);
        $style->success('Loaded ' . $generator->getRegistry()->count() . ' guideline(s)');

        // Step 3: Generate CLAUDE.md
        $claudeMdPath = $basePath . '/CLAUDE.md';
        $claudeMdContent = $generator->generateClaudeMd($packages, $basePath);

        if ($dryRun) {
            $style->info('[dry-run] Would write CLAUDE.md (' . strlen($claudeMdContent) . ' bytes)');
        } else {
            if (file_exists($claudeMdPath) && !$force) {
                $style->warning('CLAUDE.md already exists. Use --force to overwrite.');
            } else {
                file_put_contents($claudeMdPath, $claudeMdContent);
                $style->success('Generated CLAUDE.md');
            }
        }

        // Step 4: Generate .mcp.json
        $mcpJsonPath = $basePath . '/.mcp.json';
        $mcpJsonContent = $generator->generateMcpJson($basePath);

        if ($dryRun) {
            $style->info('[dry-run] Would write .mcp.json (' . strlen($mcpJsonContent) . ' bytes)');
        } else {
            if (file_exists($mcpJsonPath) && !$force) {
                $style->warning('.mcp.json already exists. Use --force to overwrite.');
            } else {
                file_put_contents($mcpJsonPath, $mcpJsonContent);
                $style->success('Generated .mcp.json');
            }
        }

        // Step 5: Create .ai/guidelines/ directory
        $guidelinesDir = $basePath . '/.ai/guidelines';

        if ($dryRun) {
            $style->info('[dry-run] Would create directory .ai/guidelines/');
        } else {
            if (!is_dir($guidelinesDir)) {
                mkdir($guidelinesDir, 0755, true);
                $style->success('Created .ai/guidelines/ directory');
            }
        }

        // Step 6: Create .ai/skills/ directory
        $skillsDir = $basePath . '/.ai/skills';

        if ($dryRun) {
            $style->info('[dry-run] Would create directory .ai/skills/');
        } else {
            if (!is_dir($skillsDir)) {
                mkdir($skillsDir, 0755, true);
                $style->success('Created .ai/skills/ directory');
            }
        }

        // Summary
        $style->newLine();
        $style->header('Summary');
        $style->newLine();
        $style->keyValue('Packages detected', (string) count($packages));
        $style->keyValue('Guidelines loaded', (string) $generator->getRegistry()->count());
        $style->keyValue('Mode', $dryRun ? 'dry-run' : 'write');
        $style->newLine();

        if ($dryRun) {
            $style->info('Dry run complete. No files were written.');
        } else {
            $style->success('Catalyst installation complete!');
            $style->info('Run `php lattice catalyst:mcp` to start the MCP server.');
        }

        $style->newLine();

        return Command::SUCCESS;
    }
}

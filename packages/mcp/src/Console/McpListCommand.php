<?php

declare(strict_types=1);

namespace Lattice\Mcp\Console;

use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class McpListCommand extends Command
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry = new ToolRegistry(),
        private readonly ResourceRegistry $resourceRegistry = new ResourceRegistry(),
        private readonly PromptRegistry $promptRegistry = new PromptRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('mcp:list')
            ->setDescription('List registered MCP tools, resources, and prompts')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by type (tools|resources|prompts)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $typeFilter = $input->getOption('type');
        $asJson = $input->getOption('json');

        if ($asJson) {
            return $this->outputJson($output, $typeFilter);
        }

        return $this->outputTable($output, $typeFilter);
    }

    private function outputJson(OutputInterface $output, ?string $typeFilter): int
    {
        $data = [];

        if ($typeFilter === null || $typeFilter === 'tools') {
            $data['tools'] = $this->toolRegistry->toList();
        }

        if ($typeFilter === null || $typeFilter === 'resources') {
            $data['resources'] = $this->resourceRegistry->toList();
        }

        if ($typeFilter === null || $typeFilter === 'prompts') {
            $data['prompts'] = $this->promptRegistry->toList();
        }

        $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function outputTable(OutputInterface $output, ?string $typeFilter): int
    {
        if ($typeFilter === null || $typeFilter === 'tools') {
            $this->listTools($output);
        }

        if ($typeFilter === null || $typeFilter === 'resources') {
            $this->listResources($output);
        }

        if ($typeFilter === null || $typeFilter === 'prompts') {
            $this->listPrompts($output);
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Total: %d tools, %d resources, %d prompts</info>',
            $this->toolRegistry->count(),
            $this->resourceRegistry->count(),
            $this->promptRegistry->count(),
        ));

        return Command::SUCCESS;
    }

    private function listTools(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Tools:</comment>');

        if ($this->toolRegistry->count() === 0) {
            $output->writeln('  (none)');

            return;
        }

        foreach ($this->toolRegistry->all() as $tool) {
            $paramCount = 0;

            if (isset($tool->inputSchema['properties']) && is_array($tool->inputSchema['properties'])) {
                $paramCount = count($tool->inputSchema['properties']);
            }

            $output->writeln(sprintf(
                '  <info>%s</info> — %s (%d params)',
                $tool->name,
                $tool->description ?: '(no description)',
                $paramCount,
            ));
        }
    }

    private function listResources(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Resources:</comment>');

        if ($this->resourceRegistry->count() === 0) {
            $output->writeln('  (none)');

            return;
        }

        foreach ($this->resourceRegistry->all() as $resource) {
            $output->writeln(sprintf(
                '  <info>%s</info> — %s [%s]',
                $resource->uri,
                $resource->name,
                $resource->mimeType,
            ));
        }
    }

    private function listPrompts(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Prompts:</comment>');

        if ($this->promptRegistry->count() === 0) {
            $output->writeln('  (none)');

            return;
        }

        foreach ($this->promptRegistry->all() as $prompt) {
            $output->writeln(sprintf(
                '  <info>%s</info> — %s (%d args)',
                $prompt->name,
                $prompt->description ?: '(no description)',
                count($prompt->arguments),
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Mcp\Console;

use Lattice\Mcp\Protocol\McpProtocolHandler;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Transport\StdioTransport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class McpServeCommand extends Command
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
            ->setName('mcp:serve')
            ->setDescription('Start the MCP server')
            ->addOption('transport', 't', InputOption::VALUE_REQUIRED, 'Transport type (stdio|sse)', 'stdio')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port for SSE transport', '8080');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $input->getOption('transport');

        $output->writeln('<info>Lattice MCP Server v1.0.0</info>');
        $output->writeln(sprintf('Tools: %d | Resources: %d | Prompts: %d', $this->toolRegistry->count(), $this->resourceRegistry->count(), $this->promptRegistry->count()));
        $output->writeln(sprintf('Transport: %s', $transport));
        $output->writeln('');

        if ($transport === 'stdio') {
            return $this->runStdio($output);
        }

        $output->writeln('<error>Unsupported transport: ' . $transport . '</error>');

        return Command::FAILURE;
    }

    private function runStdio(OutputInterface $output): int
    {
        $handler = new McpProtocolHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
        );

        $stdio = new StdioTransport($handler);
        $stdio->start();

        return Command::SUCCESS;
    }
}

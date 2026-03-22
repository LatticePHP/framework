<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Console;

use Lattice\Catalyst\Mcp\McpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CatalystMcpCommand extends Command
{
    private ?McpServer $server;

    public function __construct(?McpServer $server = null)
    {
        parent::__construct('catalyst:mcp');
        $this->server = $server;
    }

    public function setServer(McpServer $server): void
    {
        $this->server = $server;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start the MCP (Model Context Protocol) dev tools server via stdio transport')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Enable debug logging of all JSON-RPC messages to stderr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $server = $this->server ?? $this->createServer();

        // Print startup info to stderr (stdout is reserved for JSON-RPC)
        $stderr = fopen('php://stderr', 'w');

        if ($stderr !== false) {
            fwrite($stderr, "Lattice Catalyst MCP Server\n");
            fwrite($stderr, 'Tools: ' . count($server->getTools()) . "\n");
            fwrite($stderr, "Transport: stdio\n");
            fwrite($stderr, "Ready.\n");
        }

        $server->run();

        return Command::SUCCESS;
    }

    private function createServer(): McpServer
    {
        $server = new McpServer();
        $server->registerBuiltinTools();

        return $server;
    }
}

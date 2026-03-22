<?php

declare(strict_types=1);

namespace Lattice\Mcp;

use Lattice\Mcp\Console\McpListCommand;
use Lattice\Mcp\Console\McpServeCommand;
use Lattice\Mcp\Protocol\CapabilityNegotiator;
use Lattice\Mcp\Protocol\McpProtocolHandler;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Schema\ToolSchemaGenerator;
use Lattice\Mcp\Transport\StdioTransport;

final class McpServiceProvider
{
    private readonly ToolRegistry $toolRegistry;
    private readonly ResourceRegistry $resourceRegistry;
    private readonly PromptRegistry $promptRegistry;
    private readonly ToolSchemaGenerator $schemaGenerator;

    public function __construct()
    {
        $this->schemaGenerator = new ToolSchemaGenerator();
        $this->toolRegistry = new ToolRegistry($this->schemaGenerator);
        $this->resourceRegistry = new ResourceRegistry();
        $this->promptRegistry = new PromptRegistry();
    }

    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    public function getResourceRegistry(): ResourceRegistry
    {
        return $this->resourceRegistry;
    }

    public function getPromptRegistry(): PromptRegistry
    {
        return $this->promptRegistry;
    }

    /**
     * Discover tools, resources, and prompts from a service class.
     *
     * @param class-string $className
     */
    public function discoverFromClass(string $className): void
    {
        $this->toolRegistry->discover($className);
        $this->resourceRegistry->discover($className);
        $this->promptRegistry->discover($className);
    }

    /**
     * Discover from multiple service classes.
     *
     * @param list<class-string> $classNames
     */
    public function discoverFromClasses(array $classNames): void
    {
        foreach ($classNames as $className) {
            $this->discoverFromClass($className);
        }
    }

    public function createProtocolHandler(
        string $serverName = 'lattice-mcp',
        string $serverVersion = '1.0.0',
    ): McpProtocolHandler {
        return new McpProtocolHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            serverName: $serverName,
            serverVersion: $serverVersion,
        );
    }

    public function createCapabilityNegotiator(
        string $serverName = 'lattice-mcp',
        string $serverVersion = '1.0.0',
    ): CapabilityNegotiator {
        return new CapabilityNegotiator(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $serverName,
            $serverVersion,
        );
    }

    /**
     * @return list<\Symfony\Component\Console\Command\Command>
     */
    public function commands(): array
    {
        return [
            new McpServeCommand($this->toolRegistry, $this->resourceRegistry, $this->promptRegistry),
            new McpListCommand($this->toolRegistry, $this->resourceRegistry, $this->promptRegistry),
        ];
    }
}

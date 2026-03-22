<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Console;

use Lattice\Mcp\Console\McpServeCommand;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Tests\Fixtures\ContactService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class McpServeCommandTest extends TestCase
{
    #[Test]
    public function test_command_exists_and_configurable(): void
    {
        $command = new McpServeCommand();

        $this->assertSame('mcp:serve', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('transport'));
        $this->assertTrue($command->getDefinition()->hasOption('port'));
    }

    #[Test]
    public function test_unsupported_transport_returns_failure(): void
    {
        $command = new McpServeCommand();
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--transport' => 'grpc']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unsupported transport', $tester->getDisplay());
    }

    #[Test]
    public function test_banner_shows_counts(): void
    {
        $toolRegistry = new ToolRegistry();
        $resourceRegistry = new ResourceRegistry();
        $promptRegistry = new PromptRegistry();

        $toolRegistry->discover(ContactService::class);
        $resourceRegistry->discover(ContactService::class);
        $promptRegistry->discover(ContactService::class);

        $command = new McpServeCommand($toolRegistry, $resourceRegistry, $promptRegistry);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        // Use unsupported transport so it returns immediately after banner
        $tester->execute(['--transport' => 'invalid']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Lattice MCP Server v1.0.0', $output);
        $this->assertStringContainsString('Tools: 3', $output);
        $this->assertStringContainsString('Resources: 2', $output);
        $this->assertStringContainsString('Prompts: 2', $output);
    }
}

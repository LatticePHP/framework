<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Console;

use Lattice\Mcp\Console\McpListCommand;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Tests\Fixtures\ContactService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class McpListCommandTest extends TestCase
{
    private ToolRegistry $toolRegistry;
    private ResourceRegistry $resourceRegistry;
    private PromptRegistry $promptRegistry;

    protected function setUp(): void
    {
        $this->toolRegistry = new ToolRegistry();
        $this->resourceRegistry = new ResourceRegistry();
        $this->promptRegistry = new PromptRegistry();

        $this->toolRegistry->discover(ContactService::class);
        $this->resourceRegistry->discover(ContactService::class);
        $this->promptRegistry->discover(ContactService::class);
    }

    #[Test]
    public function test_lists_all_types(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Tools:', $output);
        $this->assertStringContainsString('create_contact', $output);
        $this->assertStringContainsString('Resources:', $output);
        $this->assertStringContainsString('contacts://{id}', $output);
        $this->assertStringContainsString('Prompts:', $output);
        $this->assertStringContainsString('summarize_contact', $output);
        $this->assertStringContainsString('Total:', $output);
    }

    #[Test]
    public function test_filter_by_tools(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--type' => 'tools']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Tools:', $output);
        $this->assertStringContainsString('create_contact', $output);
        $this->assertStringNotContainsString('Resources:', $output);
        $this->assertStringNotContainsString('Prompts:', $output);
    }

    #[Test]
    public function test_filter_by_resources(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--type' => 'resources']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Resources:', $output);
        $this->assertStringNotContainsString('Tools:', $output);
    }

    #[Test]
    public function test_filter_by_prompts(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--type' => 'prompts']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Prompts:', $output);
        $this->assertStringNotContainsString('Tools:', $output);
    }

    #[Test]
    public function test_json_output(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--json' => true]);

        $output = $tester->getDisplay();
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('tools', $decoded);
        $this->assertArrayHasKey('resources', $decoded);
        $this->assertArrayHasKey('prompts', $decoded);
        $this->assertCount(3, $decoded['tools']);
    }

    #[Test]
    public function test_json_output_with_type_filter(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--json' => true, '--type' => 'tools']);

        $output = $tester->getDisplay();
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('tools', $decoded);
        $this->assertArrayNotHasKey('resources', $decoded);
        $this->assertArrayNotHasKey('prompts', $decoded);
    }

    #[Test]
    public function test_empty_registries(): void
    {
        $command = new McpListCommand(
            new ToolRegistry(),
            new ResourceRegistry(),
            new PromptRegistry(),
        );

        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('(none)', $output);
        $this->assertStringContainsString('Total: 0 tools, 0 resources, 0 prompts', $output);
    }

    private function createTester(): CommandTester
    {
        $command = new McpListCommand(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
        );

        $app = new Application();
        $app->addCommand($command);

        return new CommandTester($command);
    }
}

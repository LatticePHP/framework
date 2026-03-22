<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Tests\Mcp\Tools;

use Lattice\Catalyst\Mcp\Tools\ApplicationInfoTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationInfoToolTest extends TestCase
{
    #[Test]
    public function test_returns_correct_tool_metadata(): void
    {
        $tool = new ApplicationInfoTool();

        $this->assertSame('app_info', $tool->getName());
        $this->assertNotEmpty($tool->getDescription());
        $this->assertIsArray($tool->getInputSchema());
    }

    #[Test]
    public function test_returns_php_version_and_framework_info(): void
    {
        $tool = new ApplicationInfoTool([
            'framework_version' => '1.0.0',
            'environment' => 'testing',
            'debug' => true,
            'packages' => ['core' => '1.0.0', 'routing' => '1.0.0'],
            'modules' => ['App\\UserModule', 'App\\AuthModule'],
        ]);

        $result = $tool->execute([]);

        $this->assertSame(PHP_VERSION, $result['php_version']);
        $this->assertSame(PHP_SAPI, $result['php_sapi']);
        $this->assertSame('LatticePHP', $result['framework']);
        $this->assertSame('1.0.0', $result['framework_version']);
        $this->assertSame('testing', $result['environment']);
        $this->assertTrue($result['debug']);
        $this->assertCount(2, $result['packages']);
        $this->assertCount(2, $result['modules']);
    }

    #[Test]
    public function test_returns_defaults_when_no_app_info(): void
    {
        $tool = new ApplicationInfoTool();
        $result = $tool->execute([]);

        $this->assertSame(PHP_VERSION, $result['php_version']);
        $this->assertSame('LatticePHP', $result['framework']);
        $this->assertSame('1.0.0', $result['framework_version']);
        $this->assertSame('production', $result['environment']);
        $this->assertFalse($result['debug']);
        $this->assertSame([], $result['packages']);
        $this->assertSame([], $result['modules']);
    }
}

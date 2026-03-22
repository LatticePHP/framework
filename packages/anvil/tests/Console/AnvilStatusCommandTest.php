<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Console;

use Lattice\Anvil\Console\AnvilStatusCommand;
use Lattice\Anvil\Detection\DetectionResult;
use Lattice\Anvil\Detection\DetectorInterface;
use Lattice\Anvil\Detection\SystemDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AnvilStatusCommandTest extends TestCase
{
    public function test_command_name(): void
    {
        $command = new AnvilStatusCommand();
        $this->assertSame('anvil:status', $command->getName());
    }

    public function test_status_command_shows_service_table(): void
    {
        $detector = new SystemDetector([
            $this->createMockDetector('Nginx', true, '1.24.0', 'running'),
            $this->createMockDetector('PHP', true, '8.4.0', 'installed'),
            $this->createMockDetector('Redis', false),
        ]);

        $command = new AnvilStatusCommand($detector);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Nginx', $output);
        $this->assertStringContainsString('PHP', $output);
        $this->assertStringContainsString('Redis', $output);
        $this->assertStringContainsString('1.24.0', $output);
        $this->assertStringContainsString('8.4.0', $output);
        $this->assertStringContainsString('2/3 services detected', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_status_command_with_no_services(): void
    {
        $detector = new SystemDetector([]);

        $command = new AnvilStatusCommand($detector);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('0/0 services detected', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_status_command_with_all_installed(): void
    {
        $detector = new SystemDetector([
            $this->createMockDetector('Nginx', true, '1.24.0', 'running'),
            $this->createMockDetector('PHP', true, '8.4.0', 'installed'),
        ]);

        $command = new AnvilStatusCommand($detector);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('2/2 services detected', $output);
    }

    private function createMockDetector(
        string $name,
        bool $installed,
        ?string $version = null,
        string $status = 'unknown',
    ): DetectorInterface {
        return new class($name, $installed, $version, $status) implements DetectorInterface {
            public function __construct(
                private readonly string $name,
                private readonly bool $installed,
                private readonly ?string $version,
                private readonly string $status,
            ) {
            }

            public function detect(): DetectionResult
            {
                return new DetectionResult(
                    name: $this->name,
                    installed: $this->installed,
                    version: $this->version,
                    status: $this->status,
                );
            }
        };
    }
}

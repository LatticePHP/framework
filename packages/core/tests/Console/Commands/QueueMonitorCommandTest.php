<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Console\Commands\QueueMonitorCommand;
use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Queue\SerializedJob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class QueueMonitorCommandTest extends TestCase
{
    #[Test]
    public function it_constructs_with_correct_name(): void
    {
        $driver = new InMemoryDriver();
        $command = new QueueMonitorCommand($driver);

        $this->assertSame('queue:monitor', $command->getName());
        $this->assertSame('Monitor queue sizes and status', $command->getDescription());
    }

    #[Test]
    public function it_has_correct_options(): void
    {
        $driver = new InMemoryDriver();
        $command = new QueueMonitorCommand($driver);
        $def = $command->getDefinition();

        $this->assertTrue($def->hasOption('queue'));
        $this->assertTrue($def->hasOption('json'));
    }

    #[Test]
    public function it_shows_empty_queues(): void
    {
        $driver = new InMemoryDriver();
        $command = new QueueMonitorCommand($driver, ['default', 'emails']);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('default', $display);
        $this->assertStringContainsString('emails', $display);
        $this->assertStringContainsString('Total pending jobs: 0', $display);
    }

    #[Test]
    public function it_shows_queue_sizes(): void
    {
        $driver = new InMemoryDriver();
        $now = new \DateTimeImmutable();

        // Push jobs to the queue
        $job1 = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: '{}',
            attempts: 0,
            maxAttempts: 3,
            timeout: 60,
            availableAt: $now,
            createdAt: $now,
        );
        $job2 = new SerializedJob(
            id: 'job-2',
            queue: 'default',
            payload: '{}',
            attempts: 0,
            maxAttempts: 3,
            timeout: 60,
            availableAt: $now,
            createdAt: $now,
        );

        $driver->push('default', $job1);
        $driver->push('default', $job2);

        $command = new QueueMonitorCommand($driver, ['default']);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('default', $display);
        $this->assertStringContainsString('2', $display);
        $this->assertStringContainsString('Total pending jobs: 2', $display);
    }

    #[Test]
    public function it_filters_by_queue_name(): void
    {
        $driver = new InMemoryDriver();

        $command = new QueueMonitorCommand($driver, ['default', 'emails', 'notifications']);
        $tester = new CommandTester($command);
        $tester->execute(['--queue' => 'emails']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('emails', $display);
    }

    #[Test]
    public function it_outputs_json(): void
    {
        $driver = new InMemoryDriver();

        $now = new \DateTimeImmutable();
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: '{}',
            attempts: 0,
            maxAttempts: 3,
            timeout: 60,
            availableAt: $now,
            createdAt: $now,
        );
        $driver->push('default', $job);

        $command = new QueueMonitorCommand($driver, ['default']);
        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $data = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('queues', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertSame(1, $data['total']);
        $this->assertSame('default', $data['queues'][0]['name']);
        $this->assertSame(1, $data['queues'][0]['size']);
        $this->assertSame('active', $data['queues'][0]['status']);
    }

    #[Test]
    public function it_shows_idle_status_for_empty_queues(): void
    {
        $driver = new InMemoryDriver();
        $command = new QueueMonitorCommand($driver, ['default']);
        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('idle', $data['queues'][0]['status']);
    }

    #[Test]
    public function it_exposes_queues(): void
    {
        $driver = new InMemoryDriver();
        $command = new QueueMonitorCommand($driver, ['default', 'high', 'low']);

        $this->assertSame(['default', 'high', 'low'], $command->getQueues());
    }
}

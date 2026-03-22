<?php

declare(strict_types=1);

namespace Lattice\Transport\RabbitMq\Tests\Unit;

use Lattice\Transport\RabbitMq\RabbitMqConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RabbitMqConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new RabbitMqConfig();

        $this->assertSame('localhost', $config->host);
        $this->assertSame(5672, $config->port);
        $this->assertSame('guest', $config->user);
        $this->assertSame('guest', $config->password);
        $this->assertSame('/', $config->vhost);
        $this->assertSame('', $config->exchange);
        $this->assertSame('', $config->queue);
        $this->assertSame('', $config->routingKey);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new RabbitMqConfig(
            host: 'rabbit.example.com',
            port: 5673,
            user: 'admin',
            password: 'secret',
            vhost: '/production',
            exchange: 'app.events',
            queue: 'orders',
            routingKey: 'order.created',
        );

        $this->assertSame('rabbit.example.com', $config->host);
        $this->assertSame(5673, $config->port);
        $this->assertSame('admin', $config->user);
        $this->assertSame('secret', $config->password);
        $this->assertSame('/production', $config->vhost);
        $this->assertSame('app.events', $config->exchange);
        $this->assertSame('orders', $config->queue);
        $this->assertSame('order.created', $config->routingKey);
    }

    #[Test]
    public function dsnWithDefaultVhost(): void
    {
        $config = new RabbitMqConfig();

        $this->assertSame('amqp://guest:guest@localhost:5672/', $config->getDsn());
    }

    #[Test]
    public function dsnWithCustomVhost(): void
    {
        $config = new RabbitMqConfig(
            host: 'rabbit.local',
            port: 5672,
            user: 'app',
            password: 'pass',
            vhost: '/myapp',
        );

        $this->assertSame('amqp://app:pass@rabbit.local:5672/myapp', $config->getDsn());
    }
}

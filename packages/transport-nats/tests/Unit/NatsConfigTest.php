<?php

declare(strict_types=1);

namespace Lattice\Transport\Nats\Tests\Unit;

use Lattice\Transport\Nats\NatsConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NatsConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new NatsConfig();

        $this->assertSame('localhost', $config->host);
        $this->assertSame(4222, $config->port);
        $this->assertNull($config->user);
        $this->assertNull($config->password);
        $this->assertFalse($config->tls);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new NatsConfig(
            host: 'nats.example.com',
            port: 4223,
            user: 'admin',
            password: 'secret',
            tls: true,
        );

        $this->assertSame('nats.example.com', $config->host);
        $this->assertSame(4223, $config->port);
        $this->assertSame('admin', $config->user);
        $this->assertSame('secret', $config->password);
        $this->assertTrue($config->tls);
    }

    #[Test]
    public function dsnWithoutAuth(): void
    {
        $config = new NatsConfig(host: 'nats.local', port: 4222);

        $this->assertSame('nats://nats.local:4222', $config->getDsn());
    }

    #[Test]
    public function dsnWithAuth(): void
    {
        $config = new NatsConfig(host: 'nats.local', port: 4222, user: 'user', password: 'pass');

        $this->assertSame('nats://user:pass@nats.local:4222', $config->getDsn());
    }

    #[Test]
    public function dsnWithUserOnly(): void
    {
        $config = new NatsConfig(host: 'nats.local', port: 4222, user: 'tokenuser');

        $this->assertSame('nats://tokenuser@nats.local:4222', $config->getDsn());
    }

    #[Test]
    public function dsnWithTls(): void
    {
        $config = new NatsConfig(host: 'nats.local', port: 4222, tls: true);

        $this->assertSame('tls://nats.local:4222', $config->getDsn());
    }
}

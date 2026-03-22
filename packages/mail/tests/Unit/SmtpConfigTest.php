<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Unit;

use Lattice\Mail\Transport\SmtpConfig;
use PHPUnit\Framework\TestCase;

final class SmtpConfigTest extends TestCase
{
    public function test_default_values(): void
    {
        $config = new SmtpConfig();

        $this->assertSame('localhost', $config->host);
        $this->assertSame(587, $config->port);
        $this->assertNull($config->username);
        $this->assertNull($config->password);
        $this->assertSame('tls', $config->encryption);
    }

    public function test_custom_values(): void
    {
        $config = new SmtpConfig(
            host: 'smtp.example.com',
            port: 465,
            username: 'user',
            password: 'secret',
            encryption: 'ssl',
        );

        $this->assertSame('smtp.example.com', $config->host);
        $this->assertSame(465, $config->port);
        $this->assertSame('user', $config->username);
        $this->assertSame('secret', $config->password);
        $this->assertSame('ssl', $config->encryption);
    }

    public function test_no_encryption(): void
    {
        $config = new SmtpConfig(encryption: 'none');

        $this->assertSame('none', $config->encryption);
    }

    public function test_is_readonly(): void
    {
        $config = new SmtpConfig(host: 'mail.example.com');

        $reflection = new \ReflectionClass($config);
        $this->assertTrue($reflection->isReadOnly());
    }
}

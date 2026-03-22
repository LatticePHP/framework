<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Unit;

use Lattice\Mail\Mailable;
use Lattice\Mail\Transport\LogTransport;
use PHPUnit\Framework\TestCase;

final class LogTransportTest extends TestCase
{
    public function test_logs_email_details(): void
    {
        $transport = new LogTransport();
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->from('sender@example.com')
                    ->to('recipient@example.com')
                    ->subject('Test Log');
            }
        };

        $transport->send($mailable);

        $logs = $transport->getLogs();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('sender@example.com', $logs[0]);
        $this->assertStringContainsString('recipient@example.com', $logs[0]);
        $this->assertStringContainsString('Test Log', $logs[0]);
    }

    public function test_logs_multiple_emails(): void
    {
        $transport = new LogTransport();
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->from('a@example.com')
                    ->to('b@example.com')
                    ->subject('Email');
            }
        };

        $transport->send($mailable);
        $transport->send($mailable);

        $this->assertCount(2, $transport->getLogs());
    }
}

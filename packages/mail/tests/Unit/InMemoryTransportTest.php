<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Unit;

use Lattice\Mail\Mailable;
use Lattice\Mail\Transport\InMemoryTransport;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class InMemoryTransportTest extends TestCase
{
    public function test_get_sent_returns_empty_initially(): void
    {
        $transport = new InMemoryTransport();

        $this->assertSame([], $transport->getSent());
    }

    public function test_send_stores_mailable(): void
    {
        $transport = new InMemoryTransport();
        $mailable = $this->createTestMailable();

        $transport->send($mailable);

        $this->assertCount(1, $transport->getSent());
        $this->assertSame($mailable, $transport->getSent()[0]);
    }

    public function test_assert_sent_passes_when_sent(): void
    {
        $transport = new InMemoryTransport();
        $mailable = $this->createTestMailable();

        $transport->send($mailable);

        $transport->assertSent($mailable::class);
    }

    public function test_assert_sent_fails_when_not_sent(): void
    {
        $transport = new InMemoryTransport();

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $transport->assertSent('NonExistentMailable');
    }

    public function test_assert_not_sent_passes_when_not_sent(): void
    {
        $transport = new InMemoryTransport();

        $transport->assertNotSent('NonExistentMailable');
    }

    public function test_assert_not_sent_fails_when_sent(): void
    {
        $transport = new InMemoryTransport();
        $mailable = $this->createTestMailable();

        $transport->send($mailable);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $transport->assertNotSent($mailable::class);
    }

    public function test_assert_sent_count(): void
    {
        $transport = new InMemoryTransport();

        $transport->assertSentCount(0);

        $transport->send($this->createTestMailable());
        $transport->assertSentCount(1);

        $transport->send($this->createTestMailable());
        $transport->assertSentCount(2);
    }

    public function test_assert_sent_count_fails_on_mismatch(): void
    {
        $transport = new InMemoryTransport();
        $transport->send($this->createTestMailable());

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $transport->assertSentCount(5);
    }

    public function test_send_calls_build_on_mailable(): void
    {
        $transport = new InMemoryTransport();
        $mailable = $this->createTestMailable();

        $transport->send($mailable);

        // build() sets subject to 'Test Subject'
        $this->assertSame('Test Subject', $transport->getSent()[0]->getSubject());
    }

    private function createTestMailable(): Mailable
    {
        return new class extends Mailable {
            public function build(): void
            {
                $this->subject('Test Subject')
                    ->from('test@example.com')
                    ->html('<p>Test</p>');
            }
        };
    }
}

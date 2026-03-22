<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Unit;

use Lattice\Mail\Mailable;
use Lattice\Mail\MailManager;
use Lattice\Mail\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class MailManagerTest extends TestCase
{
    public function test_send_dispatches_to_transport(): void
    {
        $transport = new InMemoryTransport();
        $manager = new MailManager($transport);

        $mailable = $this->createWelcomeMail();
        $manager->send($mailable);

        $transport->assertSentCount(1);
        $transport->assertSent($mailable::class);
    }

    public function test_send_multiple_mailables(): void
    {
        $transport = new InMemoryTransport();
        $manager = new MailManager($transport);

        $manager->send($this->createWelcomeMail());
        $manager->send($this->createWelcomeMail());

        $transport->assertSentCount(2);
    }

    public function test_queue_sends_synchronously_as_baseline(): void
    {
        $transport = new InMemoryTransport();
        $manager = new MailManager($transport);

        $mailable = $this->createWelcomeMail();
        $manager->queue($mailable);

        $transport->assertSentCount(1);
    }

    public function test_queue_with_named_queue(): void
    {
        $transport = new InMemoryTransport();
        $manager = new MailManager($transport);

        $mailable = $this->createWelcomeMail();
        $manager->queue($mailable, 'emails');

        $transport->assertSentCount(1);
    }

    private function createWelcomeMail(): Mailable
    {
        return new class extends Mailable {
            public function build(): void
            {
                $this->to('user@example.com')
                    ->from('noreply@example.com')
                    ->subject('Welcome')
                    ->html('<h1>Welcome!</h1>');
            }
        };
    }
}

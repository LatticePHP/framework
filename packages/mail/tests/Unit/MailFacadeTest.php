<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Unit;

use Lattice\Mail\Facades\Mail;
use Lattice\Mail\Mailable;
use Lattice\Mail\MailManager;
use Lattice\Mail\PendingMail;
use Lattice\Mail\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class MailFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::reset();
    }

    protected function tearDown(): void
    {
        Mail::reset();
        parent::tearDown();
    }

    public function test_mail_to_returns_pending_mail(): void
    {
        $transport = new InMemoryTransport();
        Mail::setInstance(new MailManager($transport));

        $pending = Mail::to('user@example.com');

        $this->assertInstanceOf(PendingMail::class, $pending);
    }

    public function test_mail_to_send_delivers_via_transport(): void
    {
        $transport = new InMemoryTransport();
        Mail::setInstance(new MailManager($transport));

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('Test Subject')
                    ->html('<p>Hello</p>');
            }
        };

        Mail::to('user@example.com')->send($mailable);

        $sent = $transport->getSent();
        $this->assertCount(1, $sent);
        $this->assertContains('user@example.com', $sent[0]->getTo());
        $this->assertSame('Test Subject', $sent[0]->getSubject());
    }

    public function test_mail_to_with_cc_and_bcc(): void
    {
        $transport = new InMemoryTransport();
        Mail::setInstance(new MailManager($transport));

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('CC Test');
            }
        };

        Mail::to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->send($mailable);

        $sent = $transport->getSent();
        $this->assertCount(1, $sent);
        $this->assertContains('to@example.com', $sent[0]->getTo());
        $this->assertContains('cc@example.com', $sent[0]->getCc());
        $this->assertContains('bcc@example.com', $sent[0]->getBcc());
    }

    public function test_mail_send_directly(): void
    {
        $transport = new InMemoryTransport();
        Mail::setInstance(new MailManager($transport));

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('Direct Send');
            }
        };

        Mail::send($mailable);

        $transport->assertSentCount(1);
    }

    public function test_mail_fake_returns_in_memory_transport(): void
    {
        $transport = Mail::fake();

        $this->assertInstanceOf(InMemoryTransport::class, $transport);

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('Fake Test');
            }
        };

        Mail::to('test@example.com')->send($mailable);

        $transport->assertSentCount(1);
    }

    public function test_mail_fake_assert_sent(): void
    {
        $transport = Mail::fake();

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('Assert Test');
            }
        };

        Mail::send($mailable);

        $transport->assertSent(get_class($mailable));
    }
}

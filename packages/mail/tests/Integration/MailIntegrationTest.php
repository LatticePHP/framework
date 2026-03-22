<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Integration;

use Lattice\Mail\Facades\Mail;
use Lattice\Mail\Mailable;
use Lattice\Mail\MailManager;
use Lattice\Mail\PendingMail;
use Lattice\Mail\Transport\InMemoryTransport;
use Lattice\Mail\Transport\LogTransport;
use PHPUnit\Framework\TestCase;

// ────────────────────────────────────────────────────────
// Test Mailables
// ────────────────────────────────────────────────────────

final class WelcomeMail extends Mailable
{
    public function __construct(
        private readonly string $userName,
    ) {}

    public function build(): void
    {
        $this->subject('Welcome to LatticePHP')
            ->from('noreply@lattice.dev', 'LatticePHP')
            ->html("<h1>Welcome, {$this->userName}!</h1><p>Thanks for joining.</p>");
    }
}

final class InvoiceMail extends Mailable
{
    public function __construct(
        private readonly string $invoiceNumber,
        private readonly float $amount,
    ) {}

    public function build(): void
    {
        $this->subject("Invoice #{$this->invoiceNumber}")
            ->from('billing@lattice.dev', 'Lattice Billing')
            ->html("<p>Invoice #{$this->invoiceNumber} — Amount: \${$this->amount}</p>");
    }
}

final class PasswordResetMail extends Mailable
{
    public function __construct(
        private readonly string $resetToken,
    ) {}

    public function build(): void
    {
        $this->subject('Reset Your Password')
            ->from('security@lattice.dev')
            ->replyTo('support@lattice.dev')
            ->html("<p>Click <a href=\"https://lattice.dev/reset?token={$this->resetToken}\">here</a> to reset your password.</p>");
    }
}

// ────────────────────────────────────────────────────────
// Integration Tests
// ────────────────────────────────────────────────────────

final class MailIntegrationTest extends TestCase
{
    private InMemoryTransport $transport;
    private MailManager $manager;

    protected function setUp(): void
    {
        $this->transport = new InMemoryTransport();
        $this->manager = new MailManager($this->transport);
        Mail::setInstance($this->manager);
    }

    protected function tearDown(): void
    {
        Mail::reset();
    }

    // ── 1. Mailable creation ─────────────────────────────

    public function test_mailable_creation_sets_to_subject_html_after_build(): void
    {
        $mail = new WelcomeMail('Alice');
        $mail->to('alice@example.com');
        $mail->build();

        self::assertSame(['alice@example.com'], $mail->getTo());
        self::assertSame('Welcome to LatticePHP', $mail->getSubject());
        self::assertStringContains('Welcome, Alice!', $mail->getHtml());
        self::assertSame('noreply@lattice.dev', $mail->getFrom()['address']);
        self::assertSame('LatticePHP', $mail->getFrom()['name']);
    }

    // ── 2. MailManager::send() via InMemoryTransport ─────

    public function test_mail_manager_send_captures_mailable_in_memory_transport(): void
    {
        $mail = new WelcomeMail('Bob');
        $mail->to('bob@example.com');

        $this->manager->send($mail);

        self::assertCount(1, $this->transport->getSent());
        self::assertInstanceOf(WelcomeMail::class, $this->transport->getSent()[0]);
    }

    // ── 3. Mail::to()->send() via PendingMail ───────────

    public function test_pending_mail_sets_recipient_then_sends(): void
    {
        $mail = new InvoiceMail('INV-001', 99.99);

        Mail::to('customer@example.com')->send($mail);

        self::assertCount(1, $this->transport->getSent());
        $sent = $this->transport->getSent()[0];
        self::assertSame(['customer@example.com'], $sent->getTo());
        self::assertSame('Invoice #INV-001', $sent->getSubject());
    }

    // ── 4. Mail::to()->cc()->bcc() — multiple recipients ─

    public function test_pending_mail_sets_to_cc_bcc_on_mailable(): void
    {
        $mail = new WelcomeMail('Charlie');

        Mail::to('charlie@example.com')
            ->cc(['manager@example.com', 'hr@example.com'])
            ->bcc('archive@example.com')
            ->send($mail);

        $sent = $this->transport->getSent()[0];
        self::assertSame(['charlie@example.com'], $sent->getTo());
        self::assertSame(['manager@example.com', 'hr@example.com'], $sent->getCc());
        self::assertSame(['archive@example.com'], $sent->getBcc());
    }

    // ── 5. InMemoryTransport::getSent() returns all ──────

    public function test_in_memory_transport_get_sent_returns_all_sent_mails(): void
    {
        $this->manager->send((new WelcomeMail('A'))->to('a@example.com'));
        $this->manager->send((new InvoiceMail('INV-002', 50.00))->to('b@example.com'));

        $sent = $this->transport->getSent();

        self::assertCount(2, $sent);
        self::assertInstanceOf(WelcomeMail::class, $sent[0]);
        self::assertInstanceOf(InvoiceMail::class, $sent[1]);
    }

    // ── 6. InMemoryTransport::assertSent() ───────────────

    public function test_in_memory_transport_assert_sent_passes_for_sent_mailable(): void
    {
        $this->manager->send((new WelcomeMail('Dan'))->to('dan@example.com'));

        // Should not throw
        $this->transport->assertSent(WelcomeMail::class);
    }

    // ── 7. InMemoryTransport::assertSentCount() ─────────

    public function test_in_memory_transport_assert_sent_count_matches(): void
    {
        $this->manager->send((new WelcomeMail('E'))->to('e@example.com'));
        $this->manager->send((new InvoiceMail('INV-003', 10.00))->to('f@example.com'));

        $this->transport->assertSentCount(2);
    }

    // ── 8. InMemoryTransport::assertNotSent() ────────────

    public function test_in_memory_transport_assert_not_sent_passes_when_not_sent(): void
    {
        $this->manager->send((new WelcomeMail('G'))->to('g@example.com'));

        // PasswordResetMail was never sent
        $this->transport->assertNotSent(PasswordResetMail::class);
    }

    // ── 9. LogTransport writes log entry ─────────────────

    public function test_log_transport_writes_log_entry_on_send(): void
    {
        $logTransport = new LogTransport();
        $logManager = new MailManager($logTransport);

        $mail = new WelcomeMail('Helen');
        $mail->to('helen@example.com');

        $logManager->send($mail);

        $logs = $logTransport->getLogs();
        self::assertCount(1, $logs);
        self::assertStringContains('helen@example.com', $logs[0]);
        self::assertStringContains('Welcome to LatticePHP', $logs[0]);
        self::assertStringContains('noreply@lattice.dev', $logs[0]);
    }

    // ── 10. Mail facade reset between tests ──────────────

    public function test_mail_facade_reset_provides_clean_state(): void
    {
        // Send something
        $this->manager->send((new WelcomeMail('X'))->to('x@example.com'));
        self::assertCount(1, $this->transport->getSent());

        // Reset facade and set up a fresh transport
        Mail::reset();
        $freshTransport = new InMemoryTransport();
        $freshManager = new MailManager($freshTransport);
        Mail::setInstance($freshManager);

        // Fresh state should have zero sent
        self::assertCount(0, $freshTransport->getSent());

        // Sending through the facade now uses the fresh transport
        Mail::send((new InvoiceMail('INV-100', 1.00))->to('y@example.com'));
        $freshTransport->assertSentCount(1);
        $freshTransport->assertSent(InvoiceMail::class);
    }

    // ── 11. Mailable with from and replyTo ───────────────

    public function test_mailable_from_and_reply_to_headers_set_correctly(): void
    {
        $mail = new PasswordResetMail('abc123');
        $mail->to('user@example.com');
        $mail->build();

        self::assertSame('security@lattice.dev', $mail->getFrom()['address']);
        self::assertNull($mail->getFrom()['name']);
        self::assertSame('support@lattice.dev', $mail->getReplyTo());
        self::assertSame('Reset Your Password', $mail->getSubject());
        self::assertStringContains('abc123', $mail->getHtml());
    }

    // ── 12. Multiple sends — all captured ────────────────

    public function test_multiple_sends_all_captured_in_transport(): void
    {
        Mail::send((new WelcomeMail('User1'))->to('u1@example.com'));
        Mail::send((new InvoiceMail('INV-200', 200.00))->to('u2@example.com'));
        Mail::send((new PasswordResetMail('token999'))->to('u3@example.com'));

        $this->transport->assertSentCount(3);
        $this->transport->assertSent(WelcomeMail::class);
        $this->transport->assertSent(InvoiceMail::class);
        $this->transport->assertSent(PasswordResetMail::class);

        $sent = $this->transport->getSent();
        self::assertInstanceOf(WelcomeMail::class, $sent[0]);
        self::assertInstanceOf(InvoiceMail::class, $sent[1]);
        self::assertInstanceOf(PasswordResetMail::class, $sent[2]);
    }

    // ── 13. Full cycle: create → send → assert ──────────

    public function test_full_cycle_create_send_assert_verify(): void
    {
        // Create a mailable
        $mail = new WelcomeMail('FullCycleUser');

        // Send via facade with recipient
        Mail::to('fullcycle@example.com')->send($mail);

        // Assert it was sent
        $this->transport->assertSent(WelcomeMail::class);
        $this->transport->assertSentCount(1);

        // Retrieve and verify all properties
        $sent = $this->transport->getSent()[0];
        self::assertSame(['fullcycle@example.com'], $sent->getTo());
        self::assertSame('Welcome to LatticePHP', $sent->getSubject());
        self::assertStringContains('Welcome, FullCycleUser!', $sent->getHtml());
        self::assertSame('noreply@lattice.dev', $sent->getFrom()['address']);
        self::assertSame('LatticePHP', $sent->getFrom()['name']);

        // Verify other mailables were NOT sent
        $this->transport->assertNotSent(InvoiceMail::class);
        $this->transport->assertNotSent(PasswordResetMail::class);
    }

    // ── Helper ───────────────────────────────────────────

    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }
}

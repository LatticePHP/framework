<?php

declare(strict_types=1);

namespace Lattice\Notifications\Tests\Integration;

use Lattice\Mail\Mailable;
use Lattice\Mail\MailManager;
use Lattice\Mail\Transport\InMemoryTransport;
use Lattice\Notifications\AnonymousNotifiable;
use Lattice\Notifications\Channel\DatabaseChannel;
use Lattice\Notifications\Channel\MailChannel;
use Lattice\Notifications\NotifiableInterface;
use Lattice\Notifications\Notification;
use Lattice\Notifications\NotificationManager;
use Lattice\Notifications\Testing\FakeNotificationChannel;
use PHPUnit\Framework\TestCase;

// -- Test doubles defined inline --

final class InvoicePaidNotification extends Notification
{
    public function __construct(
        private readonly float $amount = 100.00,
        private readonly string $invoiceId = 'INV-001',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'invoice_paid',
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

final class InvoicePaidMailNotification extends Notification
{
    public function __construct(
        private readonly float $amount = 100.00,
        private readonly string $invoiceId = 'INV-001',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): mixed
    {
        $mailable = new InvoicePaidMailable($this->invoiceId, $this->amount);

        if ($notifiable instanceof NotifiableInterface) {
            $route = $notifiable->routeNotificationFor('mail');
            if (is_string($route)) {
                $mailable->to($route);
            }
        }

        return $mailable;
    }
}

final class MultiChannelNotification extends Notification
{
    public function __construct(
        private readonly float $amount = 100.00,
        private readonly string $invoiceId = 'INV-001',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): mixed
    {
        $mailable = new InvoicePaidMailable($this->invoiceId, $this->amount);

        if ($notifiable instanceof NotifiableInterface) {
            $route = $notifiable->routeNotificationFor('mail');
            if (is_string($route)) {
                $mailable->to($route);
            }
        }

        return $mailable;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'invoice_paid',
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
        ];
    }
}

final class InvoicePaidMailable extends Mailable
{
    public function __construct(
        private readonly string $invoiceId,
        private readonly float $amount,
    ) {}

    public function build(): void
    {
        $this
            ->subject("Invoice {$this->invoiceId} Paid")
            ->from('billing@example.com', 'Billing')
            ->html("<h1>Invoice {$this->invoiceId}</h1><p>Amount: \${$this->amount}</p>");
    }
}

final class TestNotifiable implements NotifiableInterface
{
    public function __construct(
        private readonly string $email = 'user@example.com',
        private readonly string $id = 'user-1',
    ) {}

    public function routeNotificationFor(string $channel): mixed
    {
        return match ($channel) {
            'mail' => $this->email,
            'database' => $this->id,
            default => null,
        };
    }

    public function getId(): string
    {
        return $this->id;
    }
}

// -- Test class --

final class NotificationIntegrationTest extends TestCase
{
    private NotificationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new NotificationManager();
    }

    // ---------------------------------------------------------------
    // 1. InvoicePaidNotification dispatches via database channel
    // ---------------------------------------------------------------

    public function test_invoice_paid_notification_dispatches_to_database_channel(): void
    {
        $dbChannel = new DatabaseChannel();
        $this->manager->registerChannel('database', $dbChannel);

        $notifiable = new TestNotifiable();
        $notification = new InvoicePaidNotification(250.00, 'INV-042');

        $this->manager->send($notifiable, $notification);

        $stored = $dbChannel->getStored();
        $this->assertCount(1, $stored);
        $this->assertSame($notifiable, $stored[0]['notifiable']);
        $this->assertSame('invoice_paid', $stored[0]['notification']['type']);
        $this->assertSame('INV-042', $stored[0]['notification']['invoice_id']);
        $this->assertSame(250.00, $stored[0]['notification']['amount']);
    }

    // ---------------------------------------------------------------
    // 2. NotificationManager::send() dispatches to the correct channel
    // ---------------------------------------------------------------

    public function test_notification_manager_send_dispatches_to_correct_channel(): void
    {
        $fakeDb = new FakeNotificationChannel();
        $fakeMail = new FakeNotificationChannel();
        $this->manager->registerChannel('database', $fakeDb);
        $this->manager->registerChannel('mail', $fakeMail);

        $notifiable = new TestNotifiable();
        $notification = new InvoicePaidNotification();

        // InvoicePaidNotification uses via() => ['database']
        $this->manager->send($notifiable, $notification);

        $fakeDb->assertSentCount(1);
        $fakeDb->assertSentTo($notifiable, InvoicePaidNotification::class);

        // Mail channel should NOT have been called
        $fakeMail->assertNothingSent();
    }

    // ---------------------------------------------------------------
    // 3. FakeNotificationChannel captures and assertSentTo works
    // ---------------------------------------------------------------

    public function test_fake_notification_channel_captures_and_asserts_sent_to(): void
    {
        $fake = new FakeNotificationChannel();
        $this->manager->registerChannel('database', $fake);

        $user1 = new TestNotifiable('a@example.com', 'u1');
        $user2 = new TestNotifiable('b@example.com', 'u2');

        $notification = new InvoicePaidNotification();

        $this->manager->send($user1, $notification);

        $fake->assertSentTo($user1, InvoicePaidNotification::class);
        $fake->assertNotSentTo($user2, InvoicePaidNotification::class);
        $fake->assertSentCount(1);
    }

    // ---------------------------------------------------------------
    // 4. Mail channel: Notification with toMail() sends mail
    // ---------------------------------------------------------------

    public function test_mail_channel_sends_mail_via_in_memory_transport(): void
    {
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $mailChannel = new MailChannel($mailManager);

        $this->manager->registerChannel('mail', $mailChannel);

        $notifiable = new TestNotifiable('customer@example.com');
        $notification = new InvoicePaidMailNotification(500.00, 'INV-100');

        $this->manager->send($notifiable, $notification);

        $transport->assertSentCount(1);
        $transport->assertSent(InvoicePaidMailable::class);

        $sentMail = $transport->getSent()[0];
        $this->assertSame('Invoice INV-100 Paid', $sentMail->getSubject());
        $this->assertContains('customer@example.com', $sentMail->getTo());
        $this->assertStringContains('INV-100', $sentMail->getHtml());
    }

    // ---------------------------------------------------------------
    // 5. Database channel: Notification with toDatabase() stores data
    // ---------------------------------------------------------------

    public function test_database_channel_stores_notification_data(): void
    {
        $dbChannel = new DatabaseChannel();
        $this->manager->registerChannel('database', $dbChannel);

        $notifiable = new TestNotifiable('user@test.com', 'user-99');
        $notification = new InvoicePaidNotification(75.50, 'INV-555');

        $this->manager->send($notifiable, $notification);

        $stored = $dbChannel->getStored();
        $this->assertCount(1, $stored);

        $entry = $stored[0];
        $this->assertSame($notifiable, $entry['notifiable']);
        $this->assertIsArray($entry['notification']);
        $this->assertSame('invoice_paid', $entry['notification']['type']);
        $this->assertSame('INV-555', $entry['notification']['invoice_id']);
        $this->assertSame(75.50, $entry['notification']['amount']);
    }

    // ---------------------------------------------------------------
    // 6. Anonymous notifiable: Route to email without user model
    // ---------------------------------------------------------------

    public function test_anonymous_notifiable_routes_to_email_without_user_model(): void
    {
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $mailChannel = new MailChannel($mailManager);

        $this->manager->registerChannel('mail', $mailChannel);

        $anonymous = new AnonymousNotifiable();
        $anonymous->route('mail', 'guest@example.com');

        $this->assertSame('guest@example.com', $anonymous->routeNotificationFor('mail'));
        $this->assertNull($anonymous->routeNotificationFor('sms'));

        // Send a mail notification to the anonymous notifiable
        $notification = new InvoicePaidMailNotification(10.00, 'INV-ANON');

        $this->manager->send($anonymous, $notification);

        $transport->assertSentCount(1);
        $sent = $transport->getSent()[0];
        $this->assertSame('Invoice INV-ANON Paid', $sent->getSubject());
    }

    // ---------------------------------------------------------------
    // 7. Multiple channels: via() returns ['mail', 'database'] -> both fire
    // ---------------------------------------------------------------

    public function test_multiple_channels_both_fire(): void
    {
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $mailChannel = new MailChannel($mailManager);
        $dbChannel = new DatabaseChannel();

        $this->manager->registerChannel('mail', $mailChannel);
        $this->manager->registerChannel('database', $dbChannel);

        $notifiable = new TestNotifiable('multi@example.com', 'user-multi');
        $notification = new MultiChannelNotification(300.00, 'INV-MULTI');

        $this->manager->send($notifiable, $notification);

        // Mail was sent
        $transport->assertSentCount(1);
        $transport->assertSent(InvoicePaidMailable::class);
        $sentMail = $transport->getSent()[0];
        $this->assertContains('multi@example.com', $sentMail->getTo());

        // Database was stored
        $stored = $dbChannel->getStored();
        $this->assertCount(1, $stored);
        $this->assertSame('INV-MULTI', $stored[0]['notification']['invoice_id']);
    }

    // ---------------------------------------------------------------
    // 8. FULL CYCLE: Send notification -> verify mail + database
    // ---------------------------------------------------------------

    public function test_full_cycle_mail_captured_and_database_stored(): void
    {
        // Set up both real channels
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $mailChannel = new MailChannel($mailManager);
        $dbChannel = new DatabaseChannel();

        $this->manager->registerChannel('mail', $mailChannel);
        $this->manager->registerChannel('database', $dbChannel);

        // Create notifiable and multi-channel notification
        $notifiable = new TestNotifiable('fullcycle@example.com', 'user-fc');
        $notification = new MultiChannelNotification(999.99, 'INV-FULL');

        // Send the notification
        $this->manager->send($notifiable, $notification);

        // Verify mail was captured by InMemoryTransport
        $sentMails = $transport->getSent();
        $this->assertCount(1, $sentMails);

        $mail = $sentMails[0];
        $this->assertInstanceOf(InvoicePaidMailable::class, $mail);
        $this->assertSame('Invoice INV-FULL Paid', $mail->getSubject());
        $this->assertContains('fullcycle@example.com', $mail->getTo());
        $this->assertStringContains('INV-FULL', $mail->getHtml());
        $this->assertStringContains('999.99', $mail->getHtml());

        // Verify database entry was stored
        $storedEntries = $dbChannel->getStored();
        $this->assertCount(1, $storedEntries);

        $dbEntry = $storedEntries[0];
        $this->assertSame($notifiable, $dbEntry['notifiable']);
        $this->assertSame('invoice_paid', $dbEntry['notification']['type']);
        $this->assertSame('INV-FULL', $dbEntry['notification']['invoice_id']);
        $this->assertSame(999.99, $dbEntry['notification']['amount']);
    }

    // ---------------------------------------------------------------
    // Helper: assertStringContains (for HTML content checks)
    // ---------------------------------------------------------------

    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }
}

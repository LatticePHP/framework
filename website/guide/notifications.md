---
outline: deep
---

# Notifications

LatticePHP provides a multi-channel notification system through the `lattice/notifications` package. Send notifications via email, database, or custom channels.

## Defining Notifications

```php
<?php
declare(strict_types=1);

namespace App\Notifications;

use Lattice\Notifications\Notification;

final class InvoicePaid extends Notification
{
    public function __construct(
        private readonly int $invoiceId,
        private readonly float $amount,
    ) {}

    public function via(): array
    {
        return ['mail', 'database'];
    }

    public function toMail(): array
    {
        return [
            'subject' => "Invoice #{$this->invoiceId} paid",
            'template' => 'invoice-paid',
            'data' => ['amount' => $this->amount],
        ];
    }

    public function toDatabase(): array
    {
        return [
            'type' => 'invoice.paid',
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
        ];
    }
}
```

## Sending Notifications

```php
use Lattice\Notifications\NotificationManager;

$manager = new NotificationManager($channels);

// Send to a user
$manager->send($user, new InvoicePaid(invoiceId: 1234, amount: 99.99));

// Send to an anonymous recipient
$manager->sendAnonymous(
    'alice@example.com',
    new InvoicePaid(invoiceId: 1234, amount: 99.99),
);
```

## Channels

| Channel | Class | Delivery |
|---|---|---|
| Mail | `MailChannel` | Sends via MailManager |
| Database | `DatabaseChannel` | Stores in notifications table |
| Fake | `FakeNotificationChannel` | Testing -- captures for assertions |

## Notifiable Interface

Models that receive notifications implement `NotifiableInterface`:

```php
use Lattice\Notifications\NotifiableInterface;

final class User extends Model implements NotifiableInterface
{
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function routeNotificationForDatabase(): int
    {
        return $this->id;
    }
}
```

## Testing

```php
use Lattice\Notifications\Testing\FakeNotificationChannel;

$fake = new FakeNotificationChannel();
$container->instance('notification.channel.mail', $fake);

// ... trigger notification ...

$fake->assertSent(InvoicePaid::class);
$fake->assertSentTo($user, InvoicePaid::class);
```

## Next Steps

- [Mail](mail.md) -- email transport configuration
- [Events & Listeners](events.md) -- trigger notifications from events
- [Queues & Jobs](queues.md) -- send notifications asynchronously

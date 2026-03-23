---
outline: deep
---

# Mail

LatticePHP provides email sending through the `lattice/mail` package with pluggable transports.

## Sending Mail

Use the `Mail` facade or inject `MailManager`:

```php
use Lattice\Mail\Mail;

// Simple send
Mail::to('alice@example.com')->send('welcome', [
    'name' => 'Alice',
    'loginUrl' => 'https://app.example.com/login',
]);

// With subject and from
Mail::to('alice@example.com')
    ->subject('Welcome to our platform')
    ->from('team@example.com', 'The Team')
    ->send('welcome', ['name' => 'Alice']);
```

## PendingMail

The fluent `PendingMail` API for building emails:

```php
use Lattice\Mail\PendingMail;

$mail = new PendingMail($transport);
$mail->to('alice@example.com')
    ->cc('manager@example.com')
    ->bcc('audit@example.com')
    ->subject('Invoice #1234')
    ->send('invoice', ['amount' => 99.99]);
```

## Transports

| Transport | Class | Use Case |
|---|---|---|
| SMTP | `SmtpTransport` | Production email delivery |
| Log | `LogTransport` | Development -- logs to file |
| InMemory | `InMemoryTransport` | Testing -- captures for assertions |

Configure in `config/mail.php`:

```php
return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ],
        'log' => ['transport' => 'log'],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME')),
    ],
];
```

::: tip
Use `MAIL_MAILER=log` during development. All emails are written to your log file instead of being sent, so you can inspect them without configuring SMTP.
:::

## Testing

Use `InMemoryTransport` to capture sent emails:

```php
use Lattice\Mail\Transport\InMemoryTransport;

$transport = new InMemoryTransport();
$container->instance(MailTransportInterface::class, $transport);

// ... run code that sends mail ...

$sent = $transport->getSent();
$this->assertCount(1, $sent);
$this->assertSame('alice@example.com', $sent[0]->to);
```

## Next Steps

- [Notifications](notifications.md) -- multi-channel notifications
- [Queues & Jobs](queues.md) -- send mail asynchronously
- [Configuration](configuration.md) -- mail environment variables

<?php

declare(strict_types=1);

namespace Lattice\Notifications\Tests\Unit;

use Lattice\Mail\Mailable;
use Lattice\Mail\MailManager;
use Lattice\Mail\Transport\InMemoryTransport;
use Lattice\Notifications\Channel\MailChannel;
use Lattice\Notifications\Notification;
use RuntimeException;
use PHPUnit\Framework\TestCase;

final class MailChannelTest extends TestCase
{
    public function test_send_dispatches_mailable_via_mail_manager(): void
    {
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $channel = new MailChannel($mailManager);

        $notifiable = new \stdClass();
        $notification = $this->createMailNotification();

        $channel->send($notifiable, $notification);

        $transport->assertSentCount(1);
    }

    public function test_send_throws_when_to_mail_returns_null(): void
    {
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $channel = new MailChannel($mailManager);

        $notifiable = new \stdClass();
        $notification = new class extends Notification {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return a Mailable from toMail()');

        $channel->send($notifiable, $notification);
    }

    public function test_mailable_content_is_preserved(): void
    {
        $transport = new InMemoryTransport();
        $mailManager = new MailManager($transport);
        $channel = new MailChannel($mailManager);

        $notifiable = new \stdClass();
        $notification = $this->createMailNotification();

        $channel->send($notifiable, $notification);

        $sent = $transport->getSent();
        $this->assertSame('Notification Mail', $sent[0]->getSubject());
    }

    private function createMailNotification(): Notification
    {
        return new class extends Notification {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): mixed
            {
                return new class extends Mailable {
                    public function build(): void
                    {
                        $this->to('user@example.com')
                            ->from('noreply@example.com')
                            ->subject('Notification Mail')
                            ->html('<p>You have a notification</p>');
                    }
                };
            }
        };
    }
}

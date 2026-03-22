<?php

declare(strict_types=1);

namespace Lattice\Notifications\Tests\Unit;

use InvalidArgumentException;
use Lattice\Notifications\AnonymousNotifiable;
use Lattice\Notifications\NotifiableInterface;
use Lattice\Notifications\Notification;
use Lattice\Notifications\NotificationManager;
use Lattice\Notifications\Testing\FakeNotificationChannel;
use PHPUnit\Framework\TestCase;

final class NotificationManagerTest extends TestCase
{
    public function test_send_dispatches_to_correct_channel(): void
    {
        $manager = new NotificationManager();
        $fakeChannel = new FakeNotificationChannel();
        $manager->registerChannel('fake', $fakeChannel);

        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification(['fake']);

        $manager->send($notifiable, $notification);

        $fakeChannel->assertSentCount(1);
        $fakeChannel->assertSentTo($notifiable, $notification::class);
    }

    public function test_send_dispatches_to_multiple_channels(): void
    {
        $manager = new NotificationManager();
        $channel1 = new FakeNotificationChannel();
        $channel2 = new FakeNotificationChannel();
        $manager->registerChannel('one', $channel1);
        $manager->registerChannel('two', $channel2);

        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification(['one', 'two']);

        $manager->send($notifiable, $notification);

        $channel1->assertSentCount(1);
        $channel2->assertSentCount(1);
    }

    public function test_send_now_works_identically_to_send(): void
    {
        $manager = new NotificationManager();
        $fakeChannel = new FakeNotificationChannel();
        $manager->registerChannel('fake', $fakeChannel);

        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification(['fake']);

        $manager->sendNow($notifiable, $notification);

        $fakeChannel->assertSentCount(1);
    }

    public function test_send_throws_for_unregistered_channel(): void
    {
        $manager = new NotificationManager();
        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification(['missing']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification channel [missing] is not registered.');

        $manager->send($notifiable, $notification);
    }

    public function test_route_returns_anonymous_notifiable(): void
    {
        $manager = new NotificationManager();

        $result = $manager->route('mail', 'user@example.com');

        $this->assertInstanceOf(AnonymousNotifiable::class, $result);
        $this->assertSame('user@example.com', $result->routeNotificationFor('mail'));
    }

    public function test_notification_not_sent_when_via_returns_empty(): void
    {
        $manager = new NotificationManager();
        $fakeChannel = new FakeNotificationChannel();
        $manager->registerChannel('fake', $fakeChannel);

        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification([]);

        $manager->send($notifiable, $notification);

        $fakeChannel->assertNothingSent();
    }

    private function createNotifiable(): NotifiableInterface
    {
        return new class implements NotifiableInterface {
            public function routeNotificationFor(string $channel): mixed
            {
                return 'user@example.com';
            }
        };
    }

    private function createNotification(array $channels): Notification
    {
        return new class($channels) extends Notification {
            public function __construct(private readonly array $channels) {}

            public function via(object $notifiable): array
            {
                return $this->channels;
            }
        };
    }
}

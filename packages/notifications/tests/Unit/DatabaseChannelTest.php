<?php

declare(strict_types=1);

namespace Lattice\Notifications\Tests\Unit;

use Lattice\Notifications\Channel\DatabaseChannel;
use Lattice\Notifications\Notification;
use PHPUnit\Framework\TestCase;

final class DatabaseChannelTest extends TestCase
{
    public function test_stores_notification_data(): void
    {
        $channel = new DatabaseChannel();
        $notifiable = new \stdClass();
        $notification = $this->createDatabaseNotification();

        $channel->send($notifiable, $notification);

        $stored = $channel->getStored();
        $this->assertCount(1, $stored);
        $this->assertSame($notifiable, $stored[0]['notifiable']);
        $this->assertSame(['message' => 'Hello from DB'], $stored[0]['notification']);
    }

    public function test_falls_back_to_to_array_when_to_database_returns_null(): void
    {
        $channel = new DatabaseChannel();
        $notifiable = new \stdClass();
        $notification = new class extends Notification {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['fallback' => true];
            }
        };

        $channel->send($notifiable, $notification);

        $stored = $channel->getStored();
        $this->assertSame(['fallback' => true], $stored[0]['notification']);
    }

    public function test_stores_empty_array_when_no_data_methods_overridden(): void
    {
        $channel = new DatabaseChannel();
        $notifiable = new \stdClass();
        $notification = new class extends Notification {
            public function via(object $notifiable): array
            {
                return ['database'];
            }
        };

        $channel->send($notifiable, $notification);

        $stored = $channel->getStored();
        $this->assertSame([], $stored[0]['notification']);
    }

    public function test_stores_multiple_notifications(): void
    {
        $channel = new DatabaseChannel();
        $notification = $this->createDatabaseNotification();

        $channel->send(new \stdClass(), $notification);
        $channel->send(new \stdClass(), $notification);

        $this->assertCount(2, $channel->getStored());
    }

    private function createDatabaseNotification(): Notification
    {
        return new class extends Notification {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): ?array
            {
                return ['message' => 'Hello from DB'];
            }
        };
    }
}

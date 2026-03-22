<?php

declare(strict_types=1);

namespace Lattice\Notifications\Testing;

use Lattice\Notifications\Channel\ChannelInterface;
use Lattice\Notifications\Notification;
use PHPUnit\Framework\Assert;

final class FakeNotificationChannel implements ChannelInterface
{
    /** @var list<array{notifiable: object, notification: Notification}> */
    private array $sent = [];

    public function send(object $notifiable, Notification $notification): void
    {
        $this->sent[] = [
            'notifiable' => $notifiable,
            'notification' => $notification,
        ];
    }

    /** @return list<array{notifiable: object, notification: Notification}> */
    public function getSent(): array
    {
        return $this->sent;
    }

    public function assertSentTo(object $notifiable, string $notificationClass): void
    {
        $found = false;
        foreach ($this->sent as $entry) {
            if ($entry['notifiable'] === $notifiable && $entry['notification'] instanceof $notificationClass) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Expected [{$notificationClass}] to have been sent to the given notifiable.");
    }

    public function assertNotSentTo(object $notifiable, string $notificationClass): void
    {
        foreach ($this->sent as $entry) {
            if ($entry['notifiable'] === $notifiable && $entry['notification'] instanceof $notificationClass) {
                Assert::fail("Unexpected [{$notificationClass}] was sent to the given notifiable.");
            }
        }

        Assert::assertTrue(true);
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, "Expected {$count} notification(s) to be sent, got " . count($this->sent) . '.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, 'Expected no notifications to be sent.');
    }
}

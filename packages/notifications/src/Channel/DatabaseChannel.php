<?php

declare(strict_types=1);

namespace Lattice\Notifications\Channel;

use Lattice\Notifications\Notification;

final class DatabaseChannel implements ChannelInterface
{
    /** @var list<array{notifiable: object, notification: array<string, mixed>}> */
    private array $stored = [];

    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toDatabase($notifiable) ?? $notification->toArray($notifiable);

        $this->stored[] = [
            'notifiable' => $notifiable,
            'notification' => $data,
        ];
    }

    /** @return list<array{notifiable: object, notification: array<string, mixed>}> */
    public function getStored(): array
    {
        return $this->stored;
    }
}

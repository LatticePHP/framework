<?php

declare(strict_types=1);

namespace Lattice\Notifications;

use Lattice\Notifications\Channel\ChannelInterface;
use InvalidArgumentException;

final class NotificationManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    public function registerChannel(string $name, ChannelInterface $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $this->sendNow($notifiable, $notification);
    }

    public function sendNow(object $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        foreach ($channels as $channelName) {
            $channel = $this->resolveChannel($channelName);
            $channel->send($notifiable, $notification);
        }
    }

    public function route(string $channel, mixed $route): AnonymousNotifiable
    {
        return (new AnonymousNotifiable())->route($channel, $route);
    }

    private function resolveChannel(string $name): ChannelInterface
    {
        if (!isset($this->channels[$name])) {
            throw new InvalidArgumentException("Notification channel [{$name}] is not registered.");
        }

        return $this->channels[$name];
    }
}

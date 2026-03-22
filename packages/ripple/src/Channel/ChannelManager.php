<?php

declare(strict_types=1);

namespace Lattice\Ripple\Channel;

/**
 * Manages all WebSocket channels: public, private, and presence.
 *
 * Handles subscription, unsubscription, and auto-destruction of empty
 * channels.
 */
final class ChannelManager
{
    /** @var array<string, Channel> */
    private array $channels = [];

    /**
     * Subscribe a connection to a channel.
     *
     * Creates the channel if it does not exist.
     */
    public function subscribe(string $connectionId, string $channelName): void
    {
        $channel = $this->getOrCreateChannel($channelName);
        $channel->subscribe($connectionId);
    }

    /**
     * Unsubscribe a connection from a channel.
     *
     * Destroys the channel if it becomes empty.
     */
    public function unsubscribe(string $connectionId, string $channelName): void
    {
        $channel = $this->channels[$channelName] ?? null;

        if ($channel === null) {
            return;
        }

        $channel->unsubscribe($connectionId);

        if ($channel->isEmpty()) {
            unset($this->channels[$channelName]);
        }
    }

    /**
     * Unsubscribe a connection from all channels.
     */
    public function unsubscribeAll(string $connectionId): void
    {
        foreach ($this->channels as $name => $channel) {
            $channel->unsubscribe($connectionId);

            if ($channel->isEmpty()) {
                unset($this->channels[$name]);
            }
        }
    }

    /**
     * Subscribe to a presence channel with member data.
     *
     * @param array<string, mixed> $userInfo
     */
    public function subscribePresence(
        string $connectionId,
        string $channelName,
        string $userId,
        array $userInfo = [],
    ): void {
        $channel = $this->getOrCreateChannel($channelName);

        if (!$channel instanceof PresenceChannel) {
            return;
        }

        $channel->subscribeWithMember($connectionId, $userId, $userInfo);
    }

    /**
     * Unsubscribe from a presence channel.
     *
     * Returns whether the user was fully removed (last connection left).
     */
    public function unsubscribePresence(string $connectionId, string $channelName): bool
    {
        $channel = $this->channels[$channelName] ?? null;

        if (!$channel instanceof PresenceChannel) {
            $this->unsubscribe($connectionId, $channelName);

            return true;
        }

        $member = $channel->getMember($connectionId);
        $userId = $member['user_id'] ?? null;

        $channel->unsubscribe($connectionId);

        $userFullyRemoved = $userId !== null && !$channel->isUserPresent($userId);

        if ($channel->isEmpty()) {
            unset($this->channels[$channelName]);
        }

        return $userFullyRemoved;
    }

    /**
     * Get the member data for a connection on a presence channel.
     *
     * @return array{user_id: string, user_info: array<string, mixed>}|null
     */
    public function getPresenceMember(string $channelName, string $connectionId): ?array
    {
        $channel = $this->channels[$channelName] ?? null;

        if (!$channel instanceof PresenceChannel) {
            return null;
        }

        return $channel->getMember($connectionId);
    }

    /**
     * Get the deduplicated member list for a presence channel.
     *
     * @return array<array{user_id: string, user_info: array<string, mixed>}>
     */
    public function getPresenceMembers(string $channelName): array
    {
        $channel = $this->channels[$channelName] ?? null;

        if (!$channel instanceof PresenceChannel) {
            return [];
        }

        return $channel->getMembers();
    }

    /**
     * @return array<string>
     */
    public function getSubscribers(string $channelName): array
    {
        $channel = $this->channels[$channelName] ?? null;

        return $channel?->getSubscribers() ?? [];
    }

    public function getSubscriberCount(string $channelName): int
    {
        $channel = $this->channels[$channelName] ?? null;

        return $channel?->getSubscriberCount() ?? 0;
    }

    /**
     * @return array<string>
     */
    public function getChannelNames(): array
    {
        return array_keys($this->channels);
    }

    /**
     * @return array<string, Channel>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get the channels that a connection is subscribed to.
     *
     * @return array<string>
     */
    public function getChannelsForConnection(string $connectionId): array
    {
        $result = [];

        foreach ($this->channels as $name => $channel) {
            if ($channel->hasSubscriber($connectionId)) {
                $result[] = $name;
            }
        }

        return $result;
    }

    public function getChannel(string $channelName): ?Channel
    {
        return $this->channels[$channelName] ?? null;
    }

    public function channelExists(string $channelName): bool
    {
        return isset($this->channels[$channelName]);
    }

    public function getChannelCount(): int
    {
        return count($this->channels);
    }

    /**
     * Get or create the appropriate channel type based on name prefix.
     */
    private function getOrCreateChannel(string $channelName): Channel
    {
        if (isset($this->channels[$channelName])) {
            return $this->channels[$channelName];
        }

        $channel = match (true) {
            str_starts_with($channelName, 'presence-') => new PresenceChannel($channelName),
            str_starts_with($channelName, 'private-') => new PrivateChannel($channelName),
            default => new Channel($channelName),
        };

        $this->channels[$channelName] = $channel;

        return $channel;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Ripple\Server;

use Lattice\Ripple\Broadcasting\Broadcaster;
use Lattice\Ripple\Channel\ChannelManager;

/**
 * Routes incoming WebSocket messages to the appropriate handler.
 *
 * Parses JSON wire protocol messages and dispatches subscribe, unsubscribe,
 * whisper, and other message types.
 */
final class MessageHandler
{
    public function __construct(
        private readonly ChannelManager $channelManager,
        private readonly Broadcaster $broadcaster,
        private readonly ConnectionManager $connectionManager,
    ) {}

    /**
     * Handle an incoming text message from a connection.
     *
     * @return array{event: string, data: mixed}|null Response to send back, or null.
     */
    public function handle(Connection $connection, string $message): ?array
    {
        $decoded = json_decode($message, true);

        if (!is_array($decoded) || !isset($decoded['event'])) {
            return [
                'event' => 'error',
                'data' => ['message' => 'Invalid message format: missing "event" field.'],
            ];
        }

        $event = (string) $decoded['event'];
        $data = $decoded['data'] ?? [];
        $channel = $decoded['channel'] ?? null;

        return match ($event) {
            'subscribe' => $this->handleSubscribe($connection, (string) $channel, $data),
            'unsubscribe' => $this->handleUnsubscribe($connection, (string) $channel),
            'whisper' => $this->handleWhisper($connection, (string) $channel, $data),
            default => [
                'event' => 'error',
                'data' => ['message' => sprintf('Unknown event type: %s', $event)],
            ],
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array{event: string, channel?: string, data: mixed}
     */
    private function handleSubscribe(Connection $connection, string $channel, array $data): array
    {
        if ($channel === '') {
            return [
                'event' => 'subscription_error',
                'data' => ['message' => 'Channel name is required.'],
            ];
        }

        $isPrivate = str_starts_with($channel, 'private-');
        $isPresence = str_starts_with($channel, 'presence-');

        if (($isPrivate || $isPresence) && !isset($data['auth'])) {
            return [
                'event' => 'subscription_error',
                'channel' => $channel,
                'data' => ['message' => 'Authentication required for this channel.'],
            ];
        }

        if ($isPresence) {
            $memberInfo = $data['member_info'] ?? [];
            $userId = (string) ($data['user_id'] ?? $connection->id);
            $this->channelManager->subscribePresence($connection->id, $channel, $userId, $memberInfo);

            $members = $this->channelManager->getPresenceMembers($channel);

            $this->broadcaster->broadcastToChannelExcept(
                $channel,
                'member_added',
                ['user_id' => $userId, 'user_info' => $memberInfo],
                $connection->id,
            );

            return [
                'event' => 'subscription_succeeded',
                'channel' => $channel,
                'data' => ['members' => $members],
            ];
        }

        $this->channelManager->subscribe($connection->id, $channel);
        $connection->subscribe($channel);

        return [
            'event' => 'subscription_succeeded',
            'channel' => $channel,
            'data' => [],
        ];
    }

    /**
     * @return array{event: string, channel: string, data: mixed}
     */
    private function handleUnsubscribe(Connection $connection, string $channel): array
    {
        if ($channel === '') {
            return [
                'event' => 'error',
                'channel' => '',
                'data' => ['message' => 'Channel name is required.'],
            ];
        }

        $isPresence = str_starts_with($channel, 'presence-');

        if ($isPresence) {
            $member = $this->channelManager->getPresenceMember($channel, $connection->id);
            $this->channelManager->unsubscribePresence($connection->id, $channel);

            if ($member !== null) {
                $this->broadcaster->broadcastToChannelExcept(
                    $channel,
                    'member_removed',
                    ['user_id' => $member['user_id'], 'user_info' => $member['user_info']],
                    $connection->id,
                );
            }
        } else {
            $this->channelManager->unsubscribe($connection->id, $channel);
        }

        $connection->unsubscribe($channel);

        return [
            'event' => 'unsubscribed',
            'channel' => $channel,
            'data' => [],
        ];
    }

    /**
     * Handle a whisper (client event) on a private or presence channel.
     *
     * @param array<string, mixed> $data
     * @return array{event: string, data: mixed}|null
     */
    private function handleWhisper(Connection $connection, string $channel, array $data): ?array
    {
        if ($channel === '') {
            return [
                'event' => 'error',
                'data' => ['message' => 'Channel name is required.'],
            ];
        }

        if (!str_starts_with($channel, 'private-') && !str_starts_with($channel, 'presence-')) {
            return [
                'event' => 'error',
                'data' => ['message' => 'Whisper is only allowed on private and presence channels.'],
            ];
        }

        if (!$connection->isSubscribedTo($channel)) {
            return [
                'event' => 'error',
                'data' => ['message' => 'You are not subscribed to this channel.'],
            ];
        }

        $whisperEvent = 'client-' . ($data['event'] ?? 'message');
        $whisperData = $data['data'] ?? [];

        $this->broadcaster->broadcastToChannelExcept(
            $channel,
            $whisperEvent,
            $whisperData,
            $connection->id,
        );

        return null;
    }
}

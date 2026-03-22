<?php

declare(strict_types=1);

namespace Lattice\Ripple\Channel;

/**
 * Presence channel that tracks member metadata.
 *
 * Channel names are prefixed with "presence-". Tracks user IDs and info
 * for each connection, supporting multiple connections per user (e.g.,
 * multiple browser tabs).
 */
final class PresenceChannel extends Channel
{
    /**
     * Member data indexed by connection ID.
     *
     * @var array<string, array{user_id: string, user_info: array<string, mixed>}>
     */
    private array $members = [];

    /**
     * Subscribe a connection with presence member data.
     *
     * @param array<string, mixed> $userInfo
     */
    public function subscribeWithMember(string $connectionId, string $userId, array $userInfo = []): void
    {
        $this->subscribe($connectionId);
        $this->members[$connectionId] = [
            'user_id' => $userId,
            'user_info' => $userInfo,
        ];
    }

    public function unsubscribe(string $connectionId): void
    {
        parent::unsubscribe($connectionId);
        unset($this->members[$connectionId]);
    }

    /**
     * Get the member data for a specific connection.
     *
     * @return array{user_id: string, user_info: array<string, mixed>}|null
     */
    public function getMember(string $connectionId): ?array
    {
        return $this->members[$connectionId] ?? null;
    }

    /**
     * Get a deduplicated member list (one entry per unique user ID).
     *
     * When a user has multiple connections, only the first connection's
     * data is returned.
     *
     * @return array<array{user_id: string, user_info: array<string, mixed>}>
     */
    public function getMembers(): array
    {
        $unique = [];

        foreach ($this->members as $member) {
            $userId = $member['user_id'];
            if (!isset($unique[$userId])) {
                $unique[$userId] = $member;
            }
        }

        return array_values($unique);
    }

    /**
     * Check if a user ID is still present on any connection.
     */
    public function isUserPresent(string $userId): bool
    {
        foreach ($this->members as $member) {
            if ($member['user_id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the count of connections for a specific user ID.
     */
    public function getUserConnectionCount(string $userId): int
    {
        $count = 0;

        foreach ($this->members as $member) {
            if ($member['user_id'] === $userId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get unique member count (deduplicated by user ID).
     */
    public function getMemberCount(): int
    {
        return count($this->getMembers());
    }
}

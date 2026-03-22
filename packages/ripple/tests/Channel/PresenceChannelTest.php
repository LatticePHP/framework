<?php

declare(strict_types=1);

namespace Lattice\Ripple\Tests\Channel;

use Lattice\Ripple\Channel\ChannelManager;
use Lattice\Ripple\Channel\PresenceChannel;
use PHPUnit\Framework\TestCase;

final class PresenceChannelTest extends TestCase
{
    // --- Direct PresenceChannel tests ---

    public function test_subscribe_with_member(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);

        $this->assertTrue($channel->hasSubscriber('conn-1'));
        $this->assertSame(1, $channel->getSubscriberCount());
    }

    public function test_get_member(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);

        $member = $channel->getMember('conn-1');
        $this->assertNotNull($member);
        $this->assertSame('user-1', $member['user_id']);
        $this->assertSame(['name' => 'Alice'], $member['user_info']);
    }

    public function test_get_member_returns_null_for_unknown_connection(): void
    {
        $channel = new PresenceChannel('presence-room');
        $this->assertNull($channel->getMember('unknown'));
    }

    public function test_get_members_deduplicated(): void
    {
        $channel = new PresenceChannel('presence-room');
        // Same user on two connections (tabs)
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-2', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-3', 'user-2', ['name' => 'Bob']);

        $members = $channel->getMembers();
        $this->assertCount(2, $members);

        $userIds = array_column($members, 'user_id');
        $this->assertContains('user-1', $userIds);
        $this->assertContains('user-2', $userIds);
    }

    public function test_get_member_count(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-2', 'user-1', ['name' => 'Alice']); // same user
        $channel->subscribeWithMember('conn-3', 'user-2', ['name' => 'Bob']);

        // Unique members
        $this->assertSame(2, $channel->getMemberCount());
        // Total connections
        $this->assertSame(3, $channel->getSubscriberCount());
    }

    public function test_is_user_present(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);

        $this->assertTrue($channel->isUserPresent('user-1'));
        $this->assertFalse($channel->isUserPresent('user-999'));
    }

    public function test_get_user_connection_count(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-2', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-3', 'user-2', ['name' => 'Bob']);

        $this->assertSame(2, $channel->getUserConnectionCount('user-1'));
        $this->assertSame(1, $channel->getUserConnectionCount('user-2'));
        $this->assertSame(0, $channel->getUserConnectionCount('user-3'));
    }

    public function test_unsubscribe_removes_member(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);

        $channel->unsubscribe('conn-1');

        $this->assertFalse($channel->hasSubscriber('conn-1'));
        $this->assertNull($channel->getMember('conn-1'));
        $this->assertFalse($channel->isUserPresent('user-1'));
    }

    public function test_user_remains_present_when_one_connection_leaves(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-2', 'user-1', ['name' => 'Alice']);

        $channel->unsubscribe('conn-1');

        $this->assertTrue($channel->isUserPresent('user-1'));
        $this->assertSame(1, $channel->getUserConnectionCount('user-1'));
    }

    public function test_user_removed_when_last_connection_leaves(): void
    {
        $channel = new PresenceChannel('presence-room');
        $channel->subscribeWithMember('conn-1', 'user-1', ['name' => 'Alice']);
        $channel->subscribeWithMember('conn-2', 'user-1', ['name' => 'Alice']);

        $channel->unsubscribe('conn-1');
        $channel->unsubscribe('conn-2');

        $this->assertFalse($channel->isUserPresent('user-1'));
        $this->assertSame(0, $channel->getMemberCount());
    }

    // --- ChannelManager integration with presence ---

    public function test_subscribe_presence_via_manager(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1', ['name' => 'Alice']);

        $members = $manager->getPresenceMembers('presence-room');
        $this->assertCount(1, $members);
        $this->assertSame('user-1', $members[0]['user_id']);
    }

    public function test_get_presence_member_via_manager(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1', ['name' => 'Alice']);

        $member = $manager->getPresenceMember('presence-room', 'conn-1');
        $this->assertNotNull($member);
        $this->assertSame('user-1', $member['user_id']);
        $this->assertSame(['name' => 'Alice'], $member['user_info']);
    }

    public function test_get_presence_member_for_non_presence_channel(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'public-channel');

        $this->assertNull($manager->getPresenceMember('public-channel', 'conn-1'));
    }

    public function test_get_presence_members_for_non_presence_channel(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'public-channel');

        $this->assertSame([], $manager->getPresenceMembers('public-channel'));
    }

    public function test_unsubscribe_presence_returns_user_fully_removed(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1', ['name' => 'Alice']);
        $manager->subscribePresence('conn-2', 'presence-room', 'user-1', ['name' => 'Alice']);

        // First disconnect — user still has another connection
        $fullyRemoved = $manager->unsubscribePresence('conn-1', 'presence-room');
        $this->assertFalse($fullyRemoved);

        // Second disconnect — user fully removed
        $fullyRemoved = $manager->unsubscribePresence('conn-2', 'presence-room');
        $this->assertTrue($fullyRemoved);
    }

    public function test_presence_channel_auto_cleanup(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-temp', 'user-1', []);

        $this->assertTrue($manager->channelExists('presence-temp'));

        $manager->unsubscribePresence('conn-1', 'presence-temp');

        $this->assertFalse($manager->channelExists('presence-temp'));
    }

    public function test_unsubscribe_all_cleans_presence_channels(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1', ['name' => 'Alice']);
        $manager->subscribe('conn-1', 'chat');

        $manager->unsubscribeAll('conn-1');

        $this->assertFalse($manager->channelExists('presence-room'));
        $this->assertFalse($manager->channelExists('chat'));
    }

    public function test_multiple_users_in_presence_channel(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1', ['name' => 'Alice']);
        $manager->subscribePresence('conn-2', 'presence-room', 'user-2', ['name' => 'Bob']);
        $manager->subscribePresence('conn-3', 'presence-room', 'user-3', ['name' => 'Charlie']);

        $members = $manager->getPresenceMembers('presence-room');
        $this->assertCount(3, $members);

        $names = array_map(fn(array $m) => $m['user_info']['name'], $members);
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertContains('Charlie', $names);
    }
}

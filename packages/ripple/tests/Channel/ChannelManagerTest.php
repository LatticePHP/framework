<?php

declare(strict_types=1);

namespace Lattice\Ripple\Tests\Channel;

use Lattice\Ripple\Channel\Channel;
use Lattice\Ripple\Channel\ChannelManager;
use Lattice\Ripple\Channel\PresenceChannel;
use Lattice\Ripple\Channel\PrivateChannel;
use PHPUnit\Framework\TestCase;

final class ChannelManagerTest extends TestCase
{
    // --- Public channel subscribe/unsubscribe ---

    public function test_subscribe_to_public_channel(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'chat');

        $this->assertContains('conn-1', $manager->getSubscribers('chat'));
        $this->assertSame(1, $manager->getSubscriberCount('chat'));
    }

    public function test_multiple_subscribers_to_same_channel(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'chat');
        $manager->subscribe('conn-2', 'chat');

        $this->assertSame(2, $manager->getSubscriberCount('chat'));
        $this->assertContains('conn-1', $manager->getSubscribers('chat'));
        $this->assertContains('conn-2', $manager->getSubscribers('chat'));
    }

    public function test_unsubscribe_from_channel(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'chat');
        $manager->subscribe('conn-2', 'chat');

        $manager->unsubscribe('conn-1', 'chat');

        $this->assertSame(1, $manager->getSubscriberCount('chat'));
        $this->assertNotContains('conn-1', $manager->getSubscribers('chat'));
    }

    public function test_unsubscribe_from_nonexistent_channel(): void
    {
        $manager = new ChannelManager();
        $manager->unsubscribe('conn-1', 'unknown');

        // Should not throw
        $this->assertSame(0, $manager->getChannelCount());
    }

    public function test_unsubscribe_all(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'chat');
        $manager->subscribe('conn-1', 'news');
        $manager->subscribe('conn-2', 'chat');

        $manager->unsubscribeAll('conn-1');

        $this->assertNotContains('conn-1', $manager->getSubscribers('chat'));
        $this->assertContains('conn-2', $manager->getSubscribers('chat'));
        $this->assertSame(0, $manager->getSubscriberCount('news'));
    }

    // --- Channel auto-cleanup ---

    public function test_channel_destroyed_when_empty(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'temp-channel');

        $this->assertTrue($manager->channelExists('temp-channel'));

        $manager->unsubscribe('conn-1', 'temp-channel');

        $this->assertFalse($manager->channelExists('temp-channel'));
    }

    // --- Channel type creation ---

    public function test_creates_public_channel_for_plain_names(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'news');

        $channel = $manager->getChannel('news');
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertNotInstanceOf(PrivateChannel::class, $channel);
        $this->assertNotInstanceOf(PresenceChannel::class, $channel);
    }

    public function test_creates_private_channel_for_private_prefix(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'private-orders');

        $channel = $manager->getChannel('private-orders');
        $this->assertInstanceOf(PrivateChannel::class, $channel);
    }

    public function test_creates_presence_channel_for_presence_prefix(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1', ['name' => 'Alice']);

        $channel = $manager->getChannel('presence-room');
        $this->assertInstanceOf(PresenceChannel::class, $channel);
    }

    // --- Channel listing ---

    public function test_get_channel_names(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'alpha');
        $manager->subscribe('conn-1', 'beta');
        $manager->subscribe('conn-2', 'gamma');

        $names = $manager->getChannelNames();
        $this->assertCount(3, $names);
        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);
        $this->assertContains('gamma', $names);
    }

    public function test_get_channels_for_connection(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'alpha');
        $manager->subscribe('conn-1', 'beta');
        $manager->subscribe('conn-2', 'alpha');
        $manager->subscribe('conn-2', 'gamma');

        $conn1Channels = $manager->getChannelsForConnection('conn-1');
        $this->assertCount(2, $conn1Channels);
        $this->assertContains('alpha', $conn1Channels);
        $this->assertContains('beta', $conn1Channels);

        $conn2Channels = $manager->getChannelsForConnection('conn-2');
        $this->assertCount(2, $conn2Channels);
        $this->assertContains('alpha', $conn2Channels);
        $this->assertContains('gamma', $conn2Channels);
    }

    public function test_get_channel_count(): void
    {
        $manager = new ChannelManager();
        $this->assertSame(0, $manager->getChannelCount());

        $manager->subscribe('conn-1', 'chat');
        $this->assertSame(1, $manager->getChannelCount());

        $manager->subscribe('conn-1', 'news');
        $this->assertSame(2, $manager->getChannelCount());
    }

    // --- Subscribers for nonexistent channel ---

    public function test_get_subscribers_for_nonexistent_channel(): void
    {
        $manager = new ChannelManager();
        $this->assertSame([], $manager->getSubscribers('nonexistent'));
        $this->assertSame(0, $manager->getSubscriberCount('nonexistent'));
    }

    // --- Channel type detection ---

    public function test_channel_type_public(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'updates');

        $channel = $manager->getChannel('updates');
        $this->assertSame('public', $channel->getType());
    }

    public function test_channel_type_private(): void
    {
        $manager = new ChannelManager();
        $manager->subscribe('conn-1', 'private-secret');

        $channel = $manager->getChannel('private-secret');
        $this->assertSame('private', $channel->getType());
    }

    public function test_channel_type_presence(): void
    {
        $manager = new ChannelManager();
        $manager->subscribePresence('conn-1', 'presence-room', 'user-1');

        $channel = $manager->getChannel('presence-room');
        $this->assertSame('presence', $channel->getType());
    }
}

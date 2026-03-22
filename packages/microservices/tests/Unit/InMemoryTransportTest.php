<?php

declare(strict_types=1);

namespace Lattice\Microservices\Tests\Unit;

use Lattice\Contracts\Messaging\TransportInterface;
use Lattice\Microservices\MessageEnvelope;
use Lattice\Microservices\Transport\InMemoryTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryTransportTest extends TestCase
{
    #[Test]
    public function it_implements_transport_interface(): void
    {
        $transport = new InMemoryTransport();
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function it_publishes_and_tracks_messages(): void
    {
        $transport = new InMemoryTransport();
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: 'hello');

        $transport->publish($envelope, 'events');

        $published = $transport->getPublished();
        $this->assertCount(1, $published);
        $this->assertSame('events', $published[0]['channel']);
        $this->assertSame($envelope, $published[0]['envelope']);
    }

    #[Test]
    public function it_subscribes_and_delivers_messages(): void
    {
        $transport = new InMemoryTransport();
        $received = [];

        $transport->subscribe('events', function ($envelope) use (&$received) {
            $received[] = $envelope;
        });

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: 'data');
        $transport->publish($envelope, 'events');

        $this->assertCount(1, $received);
        $this->assertSame($envelope, $received[0]);
    }

    #[Test]
    public function it_does_not_deliver_to_unsubscribed_channels(): void
    {
        $transport = new InMemoryTransport();
        $received = [];

        $transport->subscribe('orders', function ($envelope) use (&$received) {
            $received[] = $envelope;
        });

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: 'data');
        $transport->publish($envelope, 'events');

        $this->assertCount(0, $received);
    }

    #[Test]
    public function it_acknowledges_messages(): void
    {
        $transport = new InMemoryTransport();
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: null);

        $transport->acknowledge($envelope);

        $this->assertContains('1', $transport->getAcknowledged());
    }

    #[Test]
    public function it_rejects_messages(): void
    {
        $transport = new InMemoryTransport();
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: null);

        $transport->reject($envelope, requeue: true);

        $rejected = $transport->getRejected();
        $this->assertCount(1, $rejected);
        $this->assertSame('1', $rejected[0]['messageId']);
        $this->assertTrue($rejected[0]['requeue']);
    }

    #[Test]
    public function it_returns_subscriptions(): void
    {
        $transport = new InMemoryTransport();
        $handler = fn () => null;

        $transport->subscribe('ch1', $handler);
        $transport->subscribe('ch2', $handler);

        $subs = $transport->getSubscriptions();
        $this->assertArrayHasKey('ch1', $subs);
        $this->assertArrayHasKey('ch2', $subs);
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Microservices\Tests\Unit;

use Lattice\Microservices\HandlerMatch;
use Lattice\Microservices\MessageEnvelope;
use Lattice\Microservices\MessageRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageRouterTest extends TestCase
{
    #[Test]
    public function it_routes_exact_pattern_match(): void
    {
        $router = new MessageRouter();
        $router->register('user.created', 'App\\Handler', 'onUserCreated');

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'user.created', payload: []);
        $match = $router->route($envelope);

        $this->assertInstanceOf(HandlerMatch::class, $match);
        $this->assertSame('App\\Handler', $match->handlerClass);
        $this->assertSame('onUserCreated', $match->method);
        $this->assertSame('user.created', $match->pattern);
    }

    #[Test]
    public function it_returns_null_for_unmatched_pattern(): void
    {
        $router = new MessageRouter();
        $router->register('order.placed', 'App\\Handler', 'onOrder');

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'user.created', payload: []);

        $this->assertNull($router->route($envelope));
    }

    #[Test]
    public function it_supports_wildcard_patterns(): void
    {
        $router = new MessageRouter();
        $router->register('user.*', 'App\\UserHandler', 'handle');

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'user.updated', payload: []);
        $match = $router->route($envelope);

        $this->assertNotNull($match);
        $this->assertSame('App\\UserHandler', $match->handlerClass);
    }

    #[Test]
    public function it_prefers_exact_match_over_wildcard(): void
    {
        $router = new MessageRouter();
        $router->register('user.*', 'App\\WildcardHandler', 'handle');
        $router->register('user.created', 'App\\ExactHandler', 'handle');

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'user.created', payload: []);
        $match = $router->route($envelope);

        $this->assertSame('App\\ExactHandler', $match->handlerClass);
    }

    #[Test]
    public function it_supports_double_wildcard_patterns(): void
    {
        $router = new MessageRouter();
        $router->register('order.**', 'App\\OrderHandler', 'handle');

        $envelope = new MessageEnvelope(messageId: '1', messageType: 'order.item.added', payload: []);
        $match = $router->route($envelope);

        $this->assertNotNull($match);
        $this->assertSame('App\\OrderHandler', $match->handlerClass);
    }
}

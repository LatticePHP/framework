<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\Bus;

use Lattice\Core\Bus\Attributes\QueryHandler;
use Lattice\Core\Bus\QueryBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryBusTest extends TestCase
{
    protected function setUp(): void
    {
        QueryBus::reset();
    }

    #[Test]
    public function test_register_and_dispatch_query_with_callable(): void
    {
        QueryBus::register(GetUserQuery::class, fn(GetUserQuery $q) => ['id' => $q->id, 'name' => 'Alice']);

        $result = QueryBus::dispatch(new GetUserQuery(1));

        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result);
    }

    #[Test]
    public function test_register_and_dispatch_with_class_string_handler(): void
    {
        QueryBus::register(GetUserQuery::class, GetUserQueryHandler::class);

        $result = QueryBus::dispatch(new GetUserQuery(42));

        $this->assertSame('user:42', $result);
    }

    #[Test]
    public function test_dispatch_unknown_query_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler registered');

        QueryBus::dispatch(new GetUserQuery(1));
    }

    #[Test]
    public function test_discover_query_handlers_from_attribute(): void
    {
        QueryBus::discover([GetUserQueryHandler::class]);

        $result = QueryBus::dispatch(new GetUserQuery(99));

        $this->assertSame('user:99', $result);
    }

    #[Test]
    public function test_middleware_executes_in_order(): void
    {
        $order = [];

        QueryBus::addMiddleware(function (object $q, callable $next) use (&$order) {
            $order[] = 'before';
            $result = $next($q);
            $order[] = 'after';
            return $result;
        });

        QueryBus::register(GetUserQuery::class, function (GetUserQuery $q) use (&$order) {
            $order[] = 'handler';
            return 'done';
        });

        QueryBus::dispatch(new GetUserQuery(1));

        $this->assertSame(['before', 'handler', 'after'], $order);
    }

    #[Test]
    public function test_reset_clears_handlers_and_middleware(): void
    {
        QueryBus::register(GetUserQuery::class, fn() => 'ok');
        QueryBus::addMiddleware(fn($q, $next) => $next($q));

        QueryBus::reset();

        $this->expectException(\RuntimeException::class);
        QueryBus::dispatch(new GetUserQuery(1));
    }
}

// Test fixtures

final class GetUserQuery
{
    public function __construct(
        public readonly int $id,
    ) {}
}

#[QueryHandler]
final class GetUserQueryHandler
{
    public function __invoke(GetUserQuery $query): string
    {
        return 'user:' . $query->id;
    }
}

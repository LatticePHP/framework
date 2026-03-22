<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\Bus;

use Lattice\Core\Bus\Attributes\CommandHandler;
use Lattice\Core\Bus\CommandBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandBusTest extends TestCase
{
    protected function setUp(): void
    {
        CommandBus::reset();
    }

    #[Test]
    public function test_register_and_dispatch_command_with_callable(): void
    {
        $dispatched = false;

        CommandBus::register(CreateUserCommand::class, function (CreateUserCommand $cmd) use (&$dispatched) {
            $dispatched = true;
            return $cmd->name;
        });

        $result = CommandBus::dispatch(new CreateUserCommand('Alice'));

        $this->assertTrue($dispatched);
        $this->assertSame('Alice', $result);
    }

    #[Test]
    public function test_register_and_dispatch_with_class_string_handler(): void
    {
        CommandBus::register(CreateUserCommand::class, CreateUserCommandHandler::class);

        $result = CommandBus::dispatch(new CreateUserCommand('Bob'));

        $this->assertSame('created:Bob', $result);
    }

    #[Test]
    public function test_dispatch_unknown_command_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler registered');

        CommandBus::dispatch(new CreateUserCommand('Alice'));
    }

    #[Test]
    public function test_discover_command_handlers_from_attribute(): void
    {
        CommandBus::discover([CreateUserCommandHandler::class]);

        $result = CommandBus::dispatch(new CreateUserCommand('Charlie'));

        $this->assertSame('created:Charlie', $result);
    }

    #[Test]
    public function test_middleware_executes_in_order(): void
    {
        $order = [];

        CommandBus::addMiddleware(function (object $cmd, callable $next) use (&$order) {
            $order[] = 'before-1';
            $result = $next($cmd);
            $order[] = 'after-1';
            return $result;
        });

        CommandBus::addMiddleware(function (object $cmd, callable $next) use (&$order) {
            $order[] = 'before-2';
            $result = $next($cmd);
            $order[] = 'after-2';
            return $result;
        });

        CommandBus::register(CreateUserCommand::class, function (CreateUserCommand $cmd) use (&$order) {
            $order[] = 'handler';
            return 'done';
        });

        CommandBus::dispatch(new CreateUserCommand('Alice'));

        $this->assertSame(['before-1', 'before-2', 'handler', 'after-2', 'after-1'], $order);
    }

    #[Test]
    public function test_transactional_middleware_commits_on_success(): void
    {
        $connection = new FakeConnection();
        $middleware = new \Lattice\Core\Bus\Middleware\TransactionalMiddleware($connection);

        CommandBus::addMiddleware($middleware);
        CommandBus::register(CreateUserCommand::class, fn(CreateUserCommand $cmd) => 'ok');

        $result = CommandBus::dispatch(new CreateUserCommand('Alice'));

        $this->assertSame('ok', $result);
        $this->assertTrue($connection->began);
        $this->assertTrue($connection->committed);
        $this->assertFalse($connection->rolledBack);
    }

    #[Test]
    public function test_transactional_middleware_rollbacks_on_failure(): void
    {
        $connection = new FakeConnection();
        $middleware = new \Lattice\Core\Bus\Middleware\TransactionalMiddleware($connection);

        CommandBus::addMiddleware($middleware);
        CommandBus::register(CreateUserCommand::class, fn() => throw new \RuntimeException('fail'));

        try {
            CommandBus::dispatch(new CreateUserCommand('Alice'));
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertTrue($connection->began);
        $this->assertFalse($connection->committed);
        $this->assertTrue($connection->rolledBack);
    }

    #[Test]
    public function test_reset_clears_handlers_and_middleware(): void
    {
        CommandBus::register(CreateUserCommand::class, fn() => 'ok');
        CommandBus::addMiddleware(fn($cmd, $next) => $next($cmd));

        CommandBus::reset();

        $this->expectException(\RuntimeException::class);
        CommandBus::dispatch(new CreateUserCommand('Alice'));
    }

    #[Test]
    public function test_discover_ignores_classes_without_attribute(): void
    {
        CommandBus::discover([NoAttributeHandler::class]);

        $this->expectException(\RuntimeException::class);
        CommandBus::dispatch(new CreateUserCommand('Alice'));
    }
}

// Test fixtures

final class CreateUserCommand
{
    public function __construct(
        public readonly string $name,
    ) {}
}

#[CommandHandler]
final class CreateUserCommandHandler
{
    public function __invoke(CreateUserCommand $command): string
    {
        return 'created:' . $command->name;
    }
}

final class NoAttributeHandler
{
    public function __invoke(CreateUserCommand $command): string
    {
        return 'no-attribute';
    }
}

final class FakeConnection
{
    public bool $began = false;
    public bool $committed = false;
    public bool $rolledBack = false;

    public function beginTransaction(): void { $this->began = true; }
    public function commit(): void { $this->committed = true; }
    public function rollBack(): void { $this->rolledBack = true; }
}

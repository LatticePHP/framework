<?php

declare(strict_types=1);

namespace Lattice\Core\Bus;

use Lattice\Core\Bus\Attributes\CommandHandler;
use ReflectionClass;

final class CommandBus
{
    /** @var array<class-string, callable|string> */
    private static array $handlers = [];

    /** @var list<callable> */
    private static array $middleware = [];

    /**
     * @param class-string $commandClass
     */
    public static function register(string $commandClass, callable|string $handler): void
    {
        self::$handlers[$commandClass] = $handler;
    }

    public static function dispatch(object $command): mixed
    {
        $handler = self::resolveHandler($command);

        $pipeline = self::$middleware;
        $pipeline[] = static fn(object $cmd) => $handler($cmd);

        return self::executePipeline($pipeline, $command);
    }

    public static function addMiddleware(callable $middleware): void
    {
        self::$middleware[] = $middleware;
    }

    /**
     * Discover handlers from classes with #[CommandHandler] attribute.
     *
     * @param list<class-string> $handlerClasses
     */
    public static function discover(array $handlerClasses): void
    {
        foreach ($handlerClasses as $handlerClass) {
            $reflection = new ReflectionClass($handlerClass);
            $attributes = $reflection->getAttributes(CommandHandler::class);

            if ($attributes === []) {
                continue;
            }

            // Find the __invoke method to determine the command class
            if (!$reflection->hasMethod('__invoke')) {
                continue;
            }

            $invokeMethod = $reflection->getMethod('__invoke');
            $params = $invokeMethod->getParameters();

            if ($params === []) {
                continue;
            }

            $paramType = $params[0]->getType();

            if ($paramType === null || $paramType->isBuiltin()) {
                continue;
            }

            /** @var \ReflectionNamedType $paramType */
            $commandClass = $paramType->getName();
            self::$handlers[$commandClass] = $handlerClass;
        }
    }

    public static function reset(): void
    {
        self::$handlers = [];
        self::$middleware = [];
    }

    private static function resolveHandler(object $command): callable
    {
        $commandClass = $command::class;

        if (!isset(self::$handlers[$commandClass])) {
            throw new \RuntimeException("No handler registered for command '{$commandClass}'");
        }

        $handler = self::$handlers[$commandClass];

        if (is_string($handler)) {
            $instance = new $handler();

            if (!is_callable($instance)) {
                throw new \RuntimeException("Handler '{$handler}' is not callable");
            }

            return $instance;
        }

        return $handler;
    }

    private static function executePipeline(array $pipeline, object $command): mixed
    {
        $runner = array_pop($pipeline);

        while ($middleware = array_pop($pipeline)) {
            $next = $runner;
            $runner = static fn(object $cmd) => $middleware($cmd, $next);
        }

        return $runner($command);
    }
}

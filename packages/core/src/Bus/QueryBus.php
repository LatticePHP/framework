<?php

declare(strict_types=1);

namespace Lattice\Core\Bus;

use Lattice\Core\Bus\Attributes\QueryHandler;
use ReflectionClass;

final class QueryBus
{
    /** @var array<class-string, callable|string> */
    private static array $handlers = [];

    /** @var list<callable> */
    private static array $middleware = [];

    /**
     * @param class-string $queryClass
     */
    public static function register(string $queryClass, callable|string $handler): void
    {
        self::$handlers[$queryClass] = $handler;
    }

    public static function dispatch(object $query): mixed
    {
        $handler = self::resolveHandler($query);

        $pipeline = self::$middleware;
        $pipeline[] = static fn(object $q) => $handler($q);

        return self::executePipeline($pipeline, $query);
    }

    public static function addMiddleware(callable $middleware): void
    {
        self::$middleware[] = $middleware;
    }

    /**
     * Discover handlers from classes with #[QueryHandler] attribute.
     *
     * @param list<class-string> $handlerClasses
     */
    public static function discover(array $handlerClasses): void
    {
        foreach ($handlerClasses as $handlerClass) {
            $reflection = new ReflectionClass($handlerClass);
            $attributes = $reflection->getAttributes(QueryHandler::class);

            if ($attributes === []) {
                continue;
            }

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
            $queryClass = $paramType->getName();
            self::$handlers[$queryClass] = $handlerClass;
        }
    }

    public static function reset(): void
    {
        self::$handlers = [];
        self::$middleware = [];
    }

    private static function resolveHandler(object $query): callable
    {
        $queryClass = $query::class;

        if (!isset(self::$handlers[$queryClass])) {
            throw new \RuntimeException("No handler registered for query '{$queryClass}'");
        }

        $handler = self::$handlers[$queryClass];

        if (is_string($handler)) {
            $instance = new $handler();

            if (!is_callable($instance)) {
                throw new \RuntimeException("Handler '{$handler}' is not callable");
            }

            return $instance;
        }

        return $handler;
    }

    private static function executePipeline(array $pipeline, object $query): mixed
    {
        $runner = array_pop($pipeline);

        while ($middleware = array_pop($pipeline)) {
            $next = $runner;
            $runner = static fn(object $q) => $middleware($q, $next);
        }

        return $runner($query);
    }
}

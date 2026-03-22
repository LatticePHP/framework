<?php

declare(strict_types=1);

namespace Lattice\Microservices;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;

final class MessageRouter
{
    /** @var array<string, array{handlerClass: string, method: string}> */
    private array $routes = [];

    public function register(string $pattern, string $handlerClass, string $method): void
    {
        $this->routes[$pattern] = [
            'handlerClass' => $handlerClass,
            'method' => $method,
        ];
    }

    public function route(MessageEnvelopeInterface $envelope): ?HandlerMatch
    {
        $messageType = $envelope->getMessageType();

        // Exact match takes priority
        if (isset($this->routes[$messageType])) {
            $route = $this->routes[$messageType];
            return new HandlerMatch($route['handlerClass'], $route['method'], $messageType);
        }

        // Wildcard matching
        foreach ($this->routes as $pattern => $route) {
            if ($this->matchesPattern($pattern, $messageType)) {
                return new HandlerMatch($route['handlerClass'], $route['method'], $pattern);
            }
        }

        return null;
    }

    private function matchesPattern(string $pattern, string $messageType): bool
    {
        // Convert pattern to regex
        // ** matches any number of segments (including dots)
        // * matches a single segment (no dots)
        $regex = str_replace('.', '\\.', $pattern);
        $regex = str_replace('\\.**', '(\\..+)?', $regex);
        $regex = str_replace('**', '.+', $regex);
        $regex = str_replace('*', '[^.]+', $regex);
        $regex = '/^' . $regex . '$/';

        return (bool) preg_match($regex, $messageType);
    }
}

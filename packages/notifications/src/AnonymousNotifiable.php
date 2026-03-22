<?php

declare(strict_types=1);

namespace Lattice\Notifications;

final class AnonymousNotifiable implements NotifiableInterface
{
    /** @var array<string, mixed> */
    private array $routes = [];

    public function route(string $channel, mixed $route): self
    {
        $this->routes[$channel] = $route;
        return $this;
    }

    public function routeNotificationFor(string $channel): mixed
    {
        return $this->routes[$channel] ?? null;
    }

    /** @return array<string, mixed> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

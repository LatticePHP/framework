<?php

declare(strict_types=1);

namespace Lattice\Routing;

final class UrlGenerator
{
    private string $baseUrl = '';

    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * Set the base URL for absolute URL generation.
     */
    public function setBaseUrl(string $url): void
    {
        $this->baseUrl = rtrim($url, '/');
    }

    /**
     * Generate a URL for a named route.
     *
     * @param array<string, string> $params Route parameters to substitute
     * @throws \InvalidArgumentException If route name is not found
     */
    public function route(string $name, array $params = []): string
    {
        $route = $this->router->getNamedRoute($name);

        if ($route === null) {
            throw new \InvalidArgumentException("Route [{$name}] is not defined.");
        }

        $path = $route->path;

        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", $value, $path);
        }

        return $path;
    }

    /**
     * Generate a path with optional query parameters.
     *
     * @param array<string, string> $query
     */
    public function to(string $path, array $query = []): string
    {
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $path;
    }

    /**
     * Generate a full absolute URL.
     */
    public function full(string $path): string
    {
        return $this->baseUrl . $path;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Http\Cors;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\Response;

final class CorsGuard implements GuardInterface
{
    public function __construct(
        private readonly CorsConfig $config,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        // If we can't extract origin, allow through (non-browser request)
        if (!$context instanceof HttpExecutionContext) {
            return true;
        }

        $origin = $context->getRequest()->getHeader('Origin');
        if ($origin === null) {
            return true;
        }

        return $this->isOriginAllowed($origin);
    }

    /**
     * Apply CORS headers to a response.
     */
    public function applyHeaders(Response $response, string $origin): Response
    {
        $headers = $response->headers;

        if ($this->isOriginAllowed($origin)) {
            $headers['Access-Control-Allow-Origin'] = in_array('*', $this->config->allowedOrigins, true)
                ? '*'
                : $origin;
        }

        if ($this->config->supportsCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if (!empty($this->config->exposedHeaders)) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $this->config->exposedHeaders);
        }

        return new Response(
            statusCode: $response->statusCode,
            headers: $headers,
            body: $response->body,
        );
    }

    /**
     * Handle a CORS preflight (OPTIONS) request.
     */
    public function handlePreflight(string $origin): Response
    {
        $headers = [];

        if ($this->isOriginAllowed($origin)) {
            $headers['Access-Control-Allow-Origin'] = in_array('*', $this->config->allowedOrigins, true)
                ? '*'
                : $origin;
        }

        $headers['Access-Control-Allow-Methods'] = implode(', ', $this->config->allowedMethods);
        $headers['Access-Control-Allow-Headers'] = implode(', ', $this->config->allowedHeaders);

        if ($this->config->maxAge > 0) {
            $headers['Access-Control-Max-Age'] = (string) $this->config->maxAge;
        }

        if ($this->config->supportsCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return new Response(statusCode: 204, headers: $headers);
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->config->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->config->allowedOrigins, true);
    }
}

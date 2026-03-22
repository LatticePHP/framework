<?php

declare(strict_types=1);

namespace Lattice\Auth;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Jwt\JwtConfig;
use Lattice\Jwt\JwtEncoder;

final class JwtAuthenticationGuard implements GuardInterface
{
    public function __construct(
        private readonly JwtEncoder $encoder,
        private readonly JwtConfig $config,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        // Extract Bearer token from request
        $request = $context->getRequest();
        $token = $request->bearerToken();

        if ($token === null) {
            return false;
        }

        try {
            $claims = $this->encoder->decode($token, $this->config->secret, $this->config->algorithm);

            // Build principal from JWT claims
            $principal = new Principal(
                id: $claims['sub'] ?? '',
                type: 'user',
                roles: $claims['roles'] ?? [],
                scopes: $claims['scopes'] ?? [],
                claims: $claims,
            );

            // Set principal on context
            if (method_exists($context, 'setPrincipal')) {
                $context->setPrincipal($principal);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

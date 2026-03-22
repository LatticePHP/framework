<?php

declare(strict_types=1);

namespace Lattice\Auth;

use Lattice\Auth\Hashing\BcryptHasher;
use Lattice\Auth\Hashing\HashManager;
use Lattice\Auth\Hashing\HasherInterface;
use Lattice\Contracts\Auth\TokenIssuerInterface;
use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Core\Support\ServiceProvider;
use Lattice\Jwt\JwtConfig;
use Lattice\Jwt\JwtEncoder;
use Lattice\Jwt\JwtTokenIssuer;
use Lattice\Jwt\RefreshTokenStoreInterface;
use Lattice\Jwt\Store\InMemoryRefreshTokenStore;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $container = $this->container;

        // JWT Config
        $container->singleton(JwtConfig::class, function () {
            return new JwtConfig(
                secret: $_ENV['JWT_SECRET'] ?? 'change-me',
                algorithm: $_ENV['JWT_ALGORITHM'] ?? 'HS256',
                accessTokenTtl: (int) ($_ENV['JWT_ACCESS_TTL'] ?? 3600),
                refreshTokenTtl: (int) ($_ENV['JWT_REFRESH_TTL'] ?? 604800),
            );
        });

        // JWT Encoder
        $container->singleton(JwtEncoder::class, function () use ($container) {
            $config = $container->make(JwtConfig::class);
            return new JwtEncoder($config->secret, $config->algorithm);
        });

        // Refresh token store
        $container->singleton(RefreshTokenStoreInterface::class, function () {
            return new InMemoryRefreshTokenStore();
        });

        // Token Issuer (binds interface to implementation)
        $container->singleton(TokenIssuerInterface::class, function () use ($container) {
            return new JwtTokenIssuer(
                $container->make(JwtEncoder::class),
                $container->make(RefreshTokenStoreInterface::class),
                $container->make(JwtConfig::class),
            );
        });
        $container->singleton(JwtTokenIssuer::class, function () use ($container) {
            return $container->make(TokenIssuerInterface::class);
        });

        // Hash Manager
        $container->singleton(HashManager::class, function () {
            return new HashManager();
        });
        $container->singleton(HasherInterface::class, function () use ($container) {
            return $container->make(HashManager::class);
        });

        // Auth Guard
        $container->singleton(JwtAuthenticationGuard::class, function () use ($container) {
            return new JwtAuthenticationGuard(
                $container->make(JwtEncoder::class),
                $container->make(JwtConfig::class),
            );
        });
    }
}

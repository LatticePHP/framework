<?php

declare(strict_types=1);

namespace Lattice\Jwt\Lcobucci;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint;
use Lattice\Jwt\Exception\ExpiredTokenException;
use Lattice\Jwt\Exception\InvalidTokenException;

/**
 * Production-recommended JWT encoder backed by lcobucci/jwt.
 *
 * Supports asymmetric key algorithms (RS256, ES256, etc.) in addition to HMAC,
 * standards-compliant token handling, and key rotation via lcobucci/jwt.
 *
 * The lightweight {@see \Lattice\Jwt\JwtEncoder} remains available for testing
 * and minimal setups that only need HS256.
 */
final class LcobucciJwtEncoder
{
    private Configuration $config;

    public function __construct(
        string $secret = '',
        string $algorithm = 'HS256',
        ?string $privateKey = null,
        ?string $publicKey = null,
    ) {
        if ($privateKey !== null && $publicKey !== null) {
            // Asymmetric (RS256, ES256, etc.)
            $signer = self::resolveAsymmetricSigner($algorithm);
            $this->config = Configuration::forAsymmetricSigner(
                $signer,
                InMemory::plainText($privateKey),
                InMemory::plainText($publicKey),
            );
        } else {
            // Symmetric (HS256, HS384, HS512)
            $signer = self::resolveSymmetricSigner($algorithm);
            $this->config = Configuration::forSymmetricSigner(
                $signer,
                InMemory::plainText($secret),
            );
        }
    }

    /**
     * Encode claims into a signed JWT string.
     *
     * @param array<string, mixed> $claims
     * @param int $ttl Time-to-live in seconds
     */
    public function encode(array $claims, int $ttl = 3600): string
    {
        $now = new DateTimeImmutable();
        $builder = $this->config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify("+{$ttl} seconds"));

        // Map registered claims to builder methods
        if (isset($claims['iss'])) {
            $builder = $builder->issuedBy((string) $claims['iss']);
        }
        if (isset($claims['aud'])) {
            $builder = $builder->permittedFor((string) $claims['aud']);
        }
        if (isset($claims['sub'])) {
            $builder = $builder->relatedTo((string) $claims['sub']);
        }
        if (isset($claims['jti'])) {
            $builder = $builder->identifiedBy((string) $claims['jti']);
        }
        if (isset($claims['nbf'])) {
            $nbf = $claims['nbf'] instanceof DateTimeImmutable
                ? $claims['nbf']
                : new DateTimeImmutable("@{$claims['nbf']}");
            $builder = $builder->canOnlyBeUsedAfter($nbf);
        }

        // All non-registered claims go as custom claims
        $registered = ['iss', 'aud', 'sub', 'jti', 'iat', 'exp', 'nbf'];
        foreach ($claims as $key => $value) {
            if (!in_array($key, $registered, true)) {
                $builder = $builder->withClaim($key, $value);
            }
        }

        return $builder
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    /**
     * Decode and validate a JWT string, returning all claims.
     *
     * @return array<string, mixed>
     * @throws InvalidTokenException
     * @throws ExpiredTokenException
     */
    public function decode(string $token): array
    {
        if ($token === '') {
            throw new InvalidTokenException('Token cannot be empty');
        }

        try {
            $parsed = $this->config->parser()->parse($token);
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Invalid token structure: ' . $e->getMessage());
        }

        if (!$parsed instanceof Plain) {
            throw new InvalidTokenException('Unsupported token type');
        }

        // Validate using configured constraints
        $constraints = $this->config->validationConstraints();

        if ($constraints !== []) {
            try {
                $this->config->validator()->assert($parsed, ...$constraints);
            } catch (\Throwable $e) {
                throw new InvalidTokenException('Token validation failed: ' . $e->getMessage());
            }
        }

        // Check expiration manually for our ExpiredTokenException
        $expiration = $parsed->claims()->get('exp');
        if ($expiration instanceof DateTimeImmutable && $expiration < new DateTimeImmutable()) {
            throw new ExpiredTokenException();
        }

        return $parsed->claims()->all();
    }

    /**
     * Access the underlying lcobucci/jwt Configuration for advanced use cases
     * (adding validation constraints, JWKS, key rotation, etc.).
     */
    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    private static function resolveSymmetricSigner(string $algorithm): Signer
    {
        return match ($algorithm) {
            'HS256' => new Signer\Hmac\Sha256(),
            'HS384' => new Signer\Hmac\Sha384(),
            'HS512' => new Signer\Hmac\Sha512(),
            default => new Signer\Hmac\Sha256(),
        };
    }

    private static function resolveAsymmetricSigner(string $algorithm): Signer
    {
        return match ($algorithm) {
            'RS256' => new Signer\Rsa\Sha256(),
            'RS384' => new Signer\Rsa\Sha384(),
            'RS512' => new Signer\Rsa\Sha512(),
            'ES256' => new Signer\Ecdsa\Sha256(),
            'ES384' => new Signer\Ecdsa\Sha384(),
            'ES512' => new Signer\Ecdsa\Sha512(),
            default => new Signer\Rsa\Sha256(),
        };
    }
}

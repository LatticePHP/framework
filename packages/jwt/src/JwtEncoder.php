<?php

declare(strict_types=1);

namespace Lattice\Jwt;

use Lattice\Jwt\Exception\ExpiredTokenException;
use Lattice\Jwt\Exception\InvalidTokenException;

final class JwtEncoder
{
    private const array ALGO_MAP = [
        'HS256' => 'sha256',
    ];

    public function encode(array $payload, string $secret, string $algo = 'HS256'): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $algo,
        ];

        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $segments[] = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signingInput = implode('.', $segments);
        $signature = $this->sign($signingInput, $secret, $algo);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decode(string $token, string $secret, string $algo = 'HS256'): array
    {
        if ($token === '') {
            throw new InvalidTokenException('Token cannot be empty');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidTokenException('Invalid token structure');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $signingInput = "{$headerB64}.{$payloadB64}";
        $signature = $this->base64UrlDecode($signatureB64);
        $expectedSignature = $this->sign($signingInput, $secret, $algo);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidTokenException('Invalid token signature');
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new InvalidTokenException('Invalid token payload');
        }

        // Validate exp
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new ExpiredTokenException();
        }

        // Validate iat
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            throw new InvalidTokenException('Token issued in the future');
        }

        return $payload;
    }

    private function sign(string $input, string $secret, string $algo): string
    {
        if (!isset(self::ALGO_MAP[$algo])) {
            throw new InvalidTokenException("Unsupported algorithm: {$algo}");
        }

        return hash_hmac(self::ALGO_MAP[$algo], $input, $secret, true);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidTokenException('Invalid base64url encoding');
        }

        return $decoded;
    }
}

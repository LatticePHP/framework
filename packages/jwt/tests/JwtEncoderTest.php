<?php

declare(strict_types=1);

namespace Lattice\Jwt\Tests;

use Lattice\Jwt\Exception\ExpiredTokenException;
use Lattice\Jwt\Exception\InvalidTokenException;
use Lattice\Jwt\JwtEncoder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JwtEncoderTest extends TestCase
{
    private JwtEncoder $encoder;
    private string $secret;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder();
        $this->secret = 'test-secret-key-that-is-long-enough';
    }

    #[Test]
    public function it_encodes_and_decodes_payload(): void
    {
        $payload = [
            'sub' => '123',
            'name' => 'John Doe',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->encoder->encode($payload, $this->secret);
        $decoded = $this->encoder->decode($token, $this->secret);

        $this->assertSame('123', $decoded['sub']);
        $this->assertSame('John Doe', $decoded['name']);
    }

    #[Test]
    public function encoded_token_has_three_parts(): void
    {
        $payload = ['sub' => '1', 'iat' => time(), 'exp' => time() + 3600];

        $token = $this->encoder->encode($payload, $this->secret);
        $parts = explode('.', $token);

        $this->assertCount(3, $parts);
    }

    #[Test]
    public function it_throws_on_invalid_token_format(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->encoder->decode('not.a.valid.token.format', $this->secret);
    }

    #[Test]
    public function it_throws_on_invalid_signature(): void
    {
        $payload = ['sub' => '1', 'iat' => time(), 'exp' => time() + 3600];
        $token = $this->encoder->encode($payload, $this->secret);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Invalid token signature');

        $this->encoder->decode($token, 'wrong-secret');
    }

    #[Test]
    public function it_throws_on_expired_token(): void
    {
        $payload = [
            'sub' => '1',
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ];

        $token = $this->encoder->encode($payload, $this->secret);

        $this->expectException(ExpiredTokenException::class);

        $this->encoder->decode($token, $this->secret);
    }

    #[Test]
    public function it_throws_on_tampered_payload(): void
    {
        $payload = ['sub' => '1', 'iat' => time(), 'exp' => time() + 3600];
        $token = $this->encoder->encode($payload, $this->secret);

        $parts = explode('.', $token);
        // Tamper with the payload
        $tampered = base64_encode(json_encode(['sub' => '999', 'iat' => time(), 'exp' => time() + 3600]));
        $parts[1] = rtrim(strtr($tampered, '+/', '-_'), '=');
        $tamperedToken = implode('.', $parts);

        $this->expectException(InvalidTokenException::class);

        $this->encoder->decode($tamperedToken, $this->secret);
    }

    #[Test]
    public function it_uses_hs256_algorithm_by_default(): void
    {
        $payload = ['sub' => '1', 'iat' => time(), 'exp' => time() + 3600];
        $token = $this->encoder->encode($payload, $this->secret);

        $parts = explode('.', $token);
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
    }

    #[Test]
    public function it_handles_empty_string_token(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->encoder->decode('', $this->secret);
    }

    #[Test]
    public function it_validates_iat_is_not_in_future(): void
    {
        $payload = [
            'sub' => '1',
            'iat' => time() + 3600, // issued in the future
            'exp' => time() + 7200,
        ];

        $token = $this->encoder->encode($payload, $this->secret);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token issued in the future');

        $this->encoder->decode($token, $this->secret);
    }

    #[Test]
    public function it_preserves_all_claims(): void
    {
        $payload = [
            'sub' => 'user-123',
            'iss' => 'lattice',
            'aud' => 'api',
            'iat' => time(),
            'exp' => time() + 3600,
            'scopes' => ['read', 'write'],
            'roles' => ['admin'],
        ];

        $token = $this->encoder->encode($payload, $this->secret);
        $decoded = $this->encoder->decode($token, $this->secret);

        $this->assertSame('user-123', $decoded['sub']);
        $this->assertSame('lattice', $decoded['iss']);
        $this->assertSame('api', $decoded['aud']);
        $this->assertSame(['read', 'write'], $decoded['scopes']);
        $this->assertSame(['admin'], $decoded['roles']);
    }
}

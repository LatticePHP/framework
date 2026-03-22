<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\PkceValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PkceValidatorTest extends TestCase
{
    private PkceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PkceValidator();
    }

    #[Test]
    public function it_validates_correct_s256_code_verifier(): void
    {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = $this->validator->computeS256Challenge($verifier);

        $this->assertTrue($this->validator->validate($verifier, $challenge, 'S256'));
    }

    #[Test]
    public function it_rejects_incorrect_code_verifier(): void
    {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = $this->validator->computeS256Challenge($verifier);

        $this->assertFalse($this->validator->validate('wrong-verifier', $challenge, 'S256'));
    }

    #[Test]
    public function it_throws_for_unsupported_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported code_challenge_method');

        $this->validator->validate('verifier', 'challenge', 'plain');
    }

    #[Test]
    public function it_computes_s256_challenge_correctly(): void
    {
        // Known test vector: SHA256('abc') = ba7816bf...
        $verifier = 'abc';
        $expected = rtrim(strtr(base64_encode(hash('sha256', 'abc', true)), '+/', '-_'), '=');

        $this->assertSame($expected, $this->validator->computeS256Challenge($verifier));
    }

    #[Test]
    public function it_generates_a_valid_code_verifier(): void
    {
        $verifier = $this->validator->generateVerifier(64);

        $this->assertSame(64, strlen($verifier));
        // Must be URL-safe characters only
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]+$/', $verifier);
    }

    #[Test]
    public function it_throws_for_verifier_too_short(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 43 and 128');

        $this->validator->generateVerifier(42);
    }

    #[Test]
    public function it_throws_for_verifier_too_long(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 43 and 128');

        $this->validator->generateVerifier(129);
    }

    #[Test]
    public function it_roundtrips_generated_verifier(): void
    {
        $verifier = $this->validator->generateVerifier(64);
        $challenge = $this->validator->computeS256Challenge($verifier);

        $this->assertTrue($this->validator->validate($verifier, $challenge));
    }
}

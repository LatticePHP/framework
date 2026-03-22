<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Unit;

use Lattice\Auth\Hashing\BcryptHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BcryptHasherTest extends TestCase
{
    private BcryptHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new BcryptHasher(rounds: 4); // Low rounds for fast tests
    }

    #[Test]
    public function test_make_returns_hashed_string(): void
    {
        $hash = $this->hasher->make('password');

        $this->assertNotEquals('password', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    #[Test]
    public function test_check_returns_true_for_valid_password(): void
    {
        $hash = $this->hasher->make('secret');

        $this->assertTrue($this->hasher->check('secret', $hash));
    }

    #[Test]
    public function test_check_returns_false_for_invalid_password(): void
    {
        $hash = $this->hasher->make('secret');

        $this->assertFalse($this->hasher->check('wrong', $hash));
    }

    #[Test]
    public function test_needs_rehash_returns_true_when_rounds_change(): void
    {
        $hash = $this->hasher->make('password');

        // Hash was made with 4 rounds, check if it needs rehash with 10
        $this->assertTrue($this->hasher->needsRehash($hash, ['rounds' => 10]));
    }

    #[Test]
    public function test_needs_rehash_returns_false_when_rounds_match(): void
    {
        $hash = $this->hasher->make('password');

        $this->assertFalse($this->hasher->needsRehash($hash, ['rounds' => 4]));
    }

    #[Test]
    public function test_different_passwords_produce_different_hashes(): void
    {
        $hash1 = $this->hasher->make('password1');
        $hash2 = $this->hasher->make('password2');

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function test_same_password_produces_different_hashes(): void
    {
        $hash1 = $this->hasher->make('password');
        $hash2 = $this->hasher->make('password');

        $this->assertNotEquals($hash1, $hash2); // Salt differs
    }

    #[Test]
    public function test_configurable_rounds(): void
    {
        $hasher = new BcryptHasher(rounds: 5);
        $hash = $hasher->make('password');

        $this->assertStringStartsWith('$2y$05$', $hash);
    }

    #[Test]
    public function test_default_rounds_is_12(): void
    {
        $hasher = new BcryptHasher();
        $hash = $hasher->make('password');

        $this->assertStringStartsWith('$2y$12$', $hash);
    }
}

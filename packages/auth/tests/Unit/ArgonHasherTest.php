<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Unit;

use Lattice\Auth\Hashing\ArgonHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArgonHasherTest extends TestCase
{
    private ArgonHasher $hasher;

    protected function setUp(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available');
        }

        $this->hasher = new ArgonHasher(memory: 1024, time: 2, threads: 1);
    }

    #[Test]
    public function test_make_returns_hashed_string(): void
    {
        $hash = $this->hasher->make('password');

        $this->assertNotEquals('password', $hash);
        $this->assertStringStartsWith('$argon2id$', $hash);
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
    public function test_needs_rehash_returns_true_when_options_change(): void
    {
        $hash = $this->hasher->make('password');

        $this->assertTrue($this->hasher->needsRehash($hash, ['time' => 8]));
    }

    #[Test]
    public function test_needs_rehash_returns_false_when_options_match(): void
    {
        $hash = $this->hasher->make('password');

        $this->assertFalse($this->hasher->needsRehash($hash, [
            'memory' => 1024,
            'time' => 2,
            'threads' => 1,
        ]));
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

        $this->assertNotEquals($hash1, $hash2);
    }
}

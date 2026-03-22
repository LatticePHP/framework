<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Unit;

use Lattice\Auth\Facades\Hash;
use Lattice\Auth\Hashing\HashManager;
use PHPUnit\Framework\TestCase;

final class HashFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hash::reset();
        Hash::setInstance(new HashManager());
    }

    protected function tearDown(): void
    {
        Hash::reset();
        parent::tearDown();
    }

    public function test_make_returns_hashed_string(): void
    {
        $hash = Hash::make('password');

        $this->assertNotSame('password', $hash);
        $this->assertNotEmpty($hash);
    }

    public function test_check_verifies_correct_password(): void
    {
        $hash = Hash::make('secret');

        $this->assertTrue(Hash::check('secret', $hash));
    }

    public function test_check_rejects_wrong_password(): void
    {
        $hash = Hash::make('correct');

        $this->assertFalse(Hash::check('wrong', $hash));
    }

    public function test_needs_rehash(): void
    {
        $hash = Hash::make('password');

        // A freshly hashed value should not need rehashing with default options
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function test_make_and_check_roundtrip(): void
    {
        $plaintext = 'my-secure-password-123!';
        $hash = Hash::make($plaintext);

        $this->assertTrue(Hash::check($plaintext, $hash));
        $this->assertFalse(Hash::check('not-the-password', $hash));
    }
}

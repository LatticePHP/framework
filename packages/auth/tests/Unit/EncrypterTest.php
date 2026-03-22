<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Unit;

use Lattice\Auth\Encryption\Encrypter;
use Lattice\Auth\Encryption\EncryptionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncrypterTest extends TestCase
{
    private Encrypter $encrypter;

    protected function setUp(): void
    {
        $key = Encrypter::generateKey();
        $this->encrypter = new Encrypter($key);
    }

    #[Test]
    public function test_encrypt_decrypt_round_trip_string(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted = $this->encrypter->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertEquals($plaintext, $this->encrypter->decrypt($encrypted));
    }

    #[Test]
    public function test_encrypt_decrypt_round_trip_array(): void
    {
        $data = ['user' => 'john', 'role' => 'admin'];
        $encrypted = $this->encrypter->encrypt($data);

        $this->assertEquals($data, $this->encrypter->decrypt($encrypted));
    }

    #[Test]
    public function test_encrypt_decrypt_round_trip_integer(): void
    {
        $encrypted = $this->encrypter->encrypt(42);

        $this->assertEquals(42, $this->encrypter->decrypt($encrypted));
    }

    #[Test]
    public function test_encrypt_without_serialization(): void
    {
        $plaintext = 'raw-string';
        $encrypted = $this->encrypter->encrypt($plaintext, serialize: false);

        $this->assertEquals($plaintext, $this->encrypter->decrypt($encrypted, unserialize: false));
    }

    #[Test]
    public function test_tamper_detection(): void
    {
        $encrypted = $this->encrypter->encrypt('secret');

        // Tamper with the payload
        $tampered = $encrypted . 'x';

        $this->expectException(EncryptionException::class);
        $this->encrypter->decrypt($tampered);
    }

    #[Test]
    public function test_decrypt_with_wrong_key_fails(): void
    {
        $encrypted = $this->encrypter->encrypt('secret');

        $otherKey = Encrypter::generateKey();
        $otherEncrypter = new Encrypter($otherKey);

        $this->expectException(EncryptionException::class);
        $otherEncrypter->decrypt($encrypted);
    }

    #[Test]
    public function test_generate_key_returns_valid_key(): void
    {
        $key = Encrypter::generateKey();

        // AES-256-GCM needs 32 bytes = 64 hex chars
        $this->assertEquals(64, strlen($key));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $key);
    }

    #[Test]
    public function test_generate_key_for_aes_128(): void
    {
        $key = Encrypter::generateKey('aes-128-gcm');

        // AES-128-GCM needs 16 bytes = 32 hex chars
        $this->assertEquals(32, strlen($key));
    }

    #[Test]
    public function test_same_plaintext_produces_different_ciphertext(): void
    {
        $encrypted1 = $this->encrypter->encrypt('hello');
        $encrypted2 = $this->encrypter->encrypt('hello');

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    #[Test]
    public function test_invalid_key_length_throws(): void
    {
        $this->expectException(EncryptionException::class);
        new Encrypter('tooshort');
    }

    #[Test]
    public function test_invalid_payload_throws(): void
    {
        $this->expectException(EncryptionException::class);
        $this->encrypter->decrypt('not-valid-base64-json');
    }
}

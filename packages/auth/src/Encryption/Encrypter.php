<?php

declare(strict_types=1);

namespace Lattice\Auth\Encryption;

final class Encrypter
{
    private readonly string $key;

    /**
     * @param string $key Hex-encoded encryption key
     * @param string $cipher OpenSSL cipher method
     */
    public function __construct(
        string $key,
        private readonly string $cipher = 'aes-256-gcm',
    ) {
        $expectedLength = self::keyLength($this->cipher) * 2; // hex = 2 chars per byte

        if (strlen($key) !== $expectedLength) {
            throw new EncryptionException(
                "Invalid key length. Expected {$expectedLength} hex characters for {$this->cipher}."
            );
        }

        $this->key = $key;
    }

    /**
     * Encrypt the given value.
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $plaintext = $serialize ? serialize($value) : (string) $value;

        $ivLength = openssl_cipher_iv_length($this->cipher);
        if ($ivLength === false) {
            throw new EncryptionException("Cannot determine IV length for cipher {$this->cipher}.");
        }

        $iv = random_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $plaintext,
            $this->cipher,
            hex2bin($this->key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        if ($encrypted === false) {
            throw new EncryptionException('Encryption failed.');
        }

        $payload = json_encode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($encrypted),
            'tag' => base64_encode($tag),
            'cipher' => $this->cipher,
        ], JSON_THROW_ON_ERROR);

        return base64_encode($payload);
    }

    /**
     * Decrypt the given payload.
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new EncryptionException('Invalid payload: base64 decode failed.');
        }

        $data = json_decode($decoded, true);
        if (!is_array($data) || !isset($data['iv'], $data['value'], $data['tag'])) {
            throw new EncryptionException('Invalid payload: missing required fields.');
        }

        $iv = base64_decode($data['iv'], true);
        $value = base64_decode($data['value'], true);
        $tag = base64_decode($data['tag'], true);

        if ($iv === false || $value === false || $tag === false) {
            throw new EncryptionException('Invalid payload: base64 decode of components failed.');
        }

        $decrypted = openssl_decrypt(
            $value,
            $data['cipher'] ?? $this->cipher,
            hex2bin($this->key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($decrypted === false) {
            throw new EncryptionException('Decryption failed. The payload may have been tampered with.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Generate a random encryption key for the given cipher.
     */
    public static function generateKey(string $cipher = 'aes-256-gcm'): string
    {
        return bin2hex(random_bytes(self::keyLength($cipher)));
    }

    /**
     * Get the required key length in bytes for a cipher.
     */
    private static function keyLength(string $cipher): int
    {
        return match (strtolower($cipher)) {
            'aes-128-gcm', 'aes-128-cbc' => 16,
            'aes-256-gcm', 'aes-256-cbc' => 32,
            default => 32,
        };
    }
}

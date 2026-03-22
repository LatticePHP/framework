<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class PkceValidator
{
    /**
     * Validates a PKCE code_verifier against a stored code_challenge.
     *
     * @param string $codeVerifier  The code_verifier from the token request
     * @param string $codeChallenge The code_challenge from the authorization request
     * @param string $method        The code_challenge_method (only S256 supported)
     */
    public function validate(string $codeVerifier, string $codeChallenge, string $method = 'S256'): bool
    {
        if ($method !== 'S256') {
            throw new \InvalidArgumentException("Unsupported code_challenge_method: {$method}. Only S256 is supported.");
        }

        $computed = $this->computeS256Challenge($codeVerifier);

        return hash_equals($computed, $codeChallenge);
    }

    /**
     * Computes the S256 code_challenge from a code_verifier.
     * BASE64URL(SHA256(ASCII(code_verifier)))
     */
    public function computeS256Challenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Generates a cryptographically random code_verifier.
     */
    public function generateVerifier(int $length = 64): string
    {
        if ($length < 43 || $length > 128) {
            throw new \InvalidArgumentException('Code verifier length must be between 43 and 128 characters');
        }

        $bytes = random_bytes((int) ceil($length * 3 / 4));

        return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, $length);
    }
}

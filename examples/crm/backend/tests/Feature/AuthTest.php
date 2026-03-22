<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * End-to-end tests for the authentication flow.
 *
 * Covers: register, login, token refresh, /me endpoint,
 * invalid credentials, and expired token handling.
 */
final class AuthTest extends TestCase
{
    // -------------------------------------------------------
    // Registration
    // -------------------------------------------------------

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'securepassword',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['user', 'access_token', 'refresh_token'],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_register_returns_422_when_email_missing(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'No Email',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_register_returns_422_when_email_already_taken(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    // -------------------------------------------------------
    // Login
    // -------------------------------------------------------

    public function test_login_returns_tokens_for_valid_credentials(): void
    {
        $this->createUser([
            'email' => 'login@example.com',
            'password' => 'correctpassword',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'correctpassword',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
        ]);
    }

    public function test_login_returns_401_for_invalid_password(): void
    {
        $this->createUser([
            'email' => 'bad@example.com',
            'password' => 'realpassword',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'bad@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_returns_401_for_nonexistent_user(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'ghost@example.com',
            'password' => 'anything',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_returns_422_when_fields_missing(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    // -------------------------------------------------------
    // Token refresh
    // -------------------------------------------------------

    public function test_refresh_returns_new_access_token(): void
    {
        $this->createUser([
            'email' => 'refresh@example.com',
            'password' => 'password123',
        ]);

        // Login first to get a refresh token
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'refresh@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $refreshToken = $loginResponse->getBody()['data']['refresh_token'] ?? null;
        $this->assertNotNull($refreshToken, 'Login should return a refresh_token');

        // Use refresh token to get new access token
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['access_token', 'token_type', 'expires_in'],
        ]);
    }

    public function test_refresh_returns_401_for_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'invalid-token-value',
        ]);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Me endpoint
    // -------------------------------------------------------

    public function test_me_returns_authenticated_user_profile(): void
    {
        // Login to get a valid token
        $this->createUser([
            'email' => 'me@example.com',
            'password' => 'password123',
            'name' => 'Me User',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'me@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $token = $loginResponse->getBody()['data']['access_token'];

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email'],
        ]);
        $response->assertJsonPath('data.email', 'me@example.com');
        $response->assertJsonPath('data.name', 'Me User');
    }

    public function test_me_returns_401_without_token(): void
    {
        $response = $this->asGuest()->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_returns_401_with_expired_token(): void
    {
        // A malformed / expired JWT should result in 401
        $response = $this->withToken('expired.jwt.token')->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_returns_401_with_tampered_token(): void
    {
        // Create a valid-looking but tampered JWT
        $tamperedToken = base64_encode('{"alg":"HS256"}') . '.'
            . base64_encode('{"sub":"999","exp":9999999999}') . '.invalidsig';

        $response = $this->withToken($tamperedToken)->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }
}

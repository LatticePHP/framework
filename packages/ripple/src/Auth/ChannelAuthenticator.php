<?php

declare(strict_types=1);

namespace Lattice\Ripple\Auth;

/**
 * Authenticates private and presence channel subscriptions.
 *
 * Manages channel authorization callbacks and validates auth tokens
 * for private/presence channel access.
 */
final class ChannelAuthenticator
{
    /** @var array<string, callable> Channel pattern => authorization callback */
    private array $channelCallbacks = [];

    private readonly string $appSecret;

    public function __construct(string $appSecret = '')
    {
        $this->appSecret = $appSecret;
    }

    /**
     * Register an authorization callback for a channel pattern.
     *
     * The callback receives ($connectionId, ...$params) and should return
     * true/false for private channels, or an array of user info for presence channels.
     *
     * @param string $pattern Channel pattern with {param} placeholders.
     * @param callable $callback Authorization callback.
     */
    public function channel(string $pattern, callable $callback): void
    {
        $this->channelCallbacks[$pattern] = $callback;
    }

    /**
     * Authorize a connection for a channel.
     *
     * @return bool|array<string, mixed> True/false for private, array of user info for presence.
     */
    public function authorize(string $connectionId, string $channelName): bool|array
    {
        foreach ($this->channelCallbacks as $pattern => $callback) {
            if ($this->matchPattern($pattern, $channelName)) {
                $params = $this->extractParams($pattern, $channelName);

                return $callback($connectionId, ...$params);
            }
        }

        return false;
    }

    /**
     * Generate an auth token for a channel subscription.
     */
    public function generateToken(string $connectionId, string $channelName): string
    {
        $payload = $connectionId . ':' . $channelName;

        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    /**
     * Validate an auth token.
     */
    public function validateToken(string $token, string $connectionId, string $channelName): bool
    {
        $expected = $this->generateToken($connectionId, $channelName);

        return hash_equals($expected, $token);
    }

    /**
     * Get all registered channel patterns.
     *
     * @return array<string, callable>
     */
    public function getChannelCallbacks(): array
    {
        return $this->channelCallbacks;
    }

    /**
     * Check if a pattern matches a channel name.
     */
    private function matchPattern(string $pattern, string $channelName): bool
    {
        $regex = $this->patternToRegex($pattern);

        return (bool) preg_match($regex, $channelName);
    }

    /**
     * Extract parameters from a channel name using a pattern.
     *
     * @return array<int, string>
     */
    private function extractParams(string $pattern, string $channelName): array
    {
        $regex = $this->patternToRegex($pattern);

        if (preg_match($regex, $channelName, $matches)) {
            array_shift($matches);

            return array_values($matches);
        }

        return [];
    }

    /**
     * Convert a channel pattern to a regex.
     */
    private function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $regex = preg_replace('/\\\\\\{[^}]+\\\\\\}/', '([^.]+)', $escaped);

        return '/^' . $regex . '$/';
    }
}

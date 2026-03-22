<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting;

use RuntimeException;

/**
 * Static facade for the broadcasting subsystem.
 *
 * Provides channel authorization registration, event broadcasting,
 * and channel pattern matching with parameter extraction.
 */
final class Broadcast
{
    /** @var array<string, callable> */
    private static array $channels = [];

    private static ?BroadcastDriverInterface $driver = null;

    /**
     * Register an authorization callback for a channel pattern.
     *
     * Channel patterns may contain {parameter} placeholders that will be
     * extracted and passed to the callback as positional arguments after $user.
     *
     * Example: Broadcast::channel('orders.{orderId}', fn($user, $orderId) => ...)
     */
    public static function channel(string $channel, callable $callback): void
    {
        self::$channels[$channel] = $callback;
    }

    /**
     * Broadcast an event through the configured driver.
     */
    public static function event(ShouldBroadcast $event): void
    {
        self::getDriver()->broadcast(
            $event->broadcastOn(),
            $event->broadcastAs(),
            $event->broadcastWith(),
        );
    }

    /**
     * Authorize a user for a specific channel.
     *
     * Iterates registered channel patterns and returns the result of the
     * first matching callback. Returns false if no pattern matches.
     *
     * @return bool|array<string, mixed>
     */
    public static function authorize(string $channel, object $user): bool|array
    {
        foreach (self::$channels as $pattern => $callback) {
            if (self::matchChannel($pattern, $channel)) {
                return $callback($user, ...self::extractParams($pattern, $channel));
            }
        }

        return false;
    }

    /**
     * Set the broadcast driver.
     */
    public static function setDriver(BroadcastDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * Get the configured broadcast driver.
     *
     * @throws RuntimeException If no driver has been configured.
     */
    public static function getDriver(): BroadcastDriverInterface
    {
        if (self::$driver === null) {
            throw new RuntimeException('No broadcast driver configured. Call Broadcast::setDriver() first.');
        }

        return self::$driver;
    }

    /**
     * Reset all state (channels and driver). Intended for testing.
     */
    public static function reset(): void
    {
        self::$channels = [];
        self::$driver = null;
    }

    /**
     * Get all registered channel patterns.
     *
     * @return array<string, callable>
     */
    public static function getChannels(): array
    {
        return self::$channels;
    }

    /**
     * Determine if a channel pattern matches a concrete channel name.
     */
    private static function matchChannel(string $pattern, string $channel): bool
    {
        $regex = self::patternToRegex($pattern);

        return (bool) preg_match($regex, $channel);
    }

    /**
     * Extract parameter values from a channel name using the given pattern.
     *
     * @return array<int, string>
     */
    private static function extractParams(string $pattern, string $channel): array
    {
        $regex = self::patternToRegex($pattern);

        if (preg_match($regex, $channel, $matches)) {
            // Remove the full match, return only captured groups
            array_shift($matches);

            return array_values($matches);
        }

        return [];
    }

    /**
     * Convert a channel pattern with {param} placeholders to a regex.
     */
    private static function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        // Restore {param} placeholders as capture groups
        $regex = preg_replace('/\\\\\\{[^}]+\\\\\\}/', '([^.]+)', $escaped);

        return '/^' . $regex . '$/';
    }
}

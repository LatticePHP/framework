<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting;

use PHPUnit\Framework\Assert;

/**
 * In-memory broadcast driver for testing.
 *
 * Captures all broadcasts and provides assertion helpers to verify
 * that expected events were (or were not) broadcast during a test.
 */
final class FakeBroadcaster implements BroadcastDriverInterface
{
    /** @var array<int, array{channels: string|array<string>, event: string, data: array<string, mixed>}> */
    private array $broadcasts = [];

    public function broadcast(string|array $channels, string $event, array $data): void
    {
        $this->broadcasts[] = compact('channels', 'event', 'data');
    }

    /**
     * Assert that an event with the given name was broadcast.
     */
    public function assertBroadcast(string $event, ?int $times = null): void
    {
        $matching = array_filter(
            $this->broadcasts,
            static fn(array $b): bool => $b['event'] === $event,
        );

        Assert::assertNotEmpty($matching, "Expected event [{$event}] was not broadcast.");

        if ($times !== null) {
            Assert::assertCount(
                $times,
                $matching,
                "Expected event [{$event}] to be broadcast {$times} time(s), but was broadcast " . count($matching) . ' time(s).',
            );
        }
    }

    /**
     * Assert that an event with the given name was NOT broadcast.
     */
    public function assertNotBroadcast(string $event): void
    {
        $matching = array_filter(
            $this->broadcasts,
            static fn(array $b): bool => $b['event'] === $event,
        );

        Assert::assertEmpty($matching, "Unexpected event [{$event}] was broadcast.");
    }

    /**
     * Assert that nothing was broadcast at all.
     */
    public function assertNothingBroadcast(): void
    {
        Assert::assertEmpty(
            $this->broadcasts,
            'Expected no broadcasts, but ' . count($this->broadcasts) . ' broadcast(s) were recorded.',
        );
    }

    /**
     * Get all recorded broadcasts.
     *
     * @return array<int, array{channels: string|array<string>, event: string, data: array<string, mixed>}>
     */
    public function getBroadcasts(): array
    {
        return $this->broadcasts;
    }

    /**
     * Clear all recorded broadcasts.
     */
    public function flush(): void
    {
        $this->broadcasts = [];
    }
}

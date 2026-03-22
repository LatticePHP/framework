<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting\Drivers;

use Lattice\Events\Broadcasting\BroadcastDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Broadcast driver that logs all broadcasts.
 *
 * Useful for development and debugging — writes every broadcast
 * event to the configured PSR-3 logger.
 */
final class LogBroadcastDriver implements BroadcastDriverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function broadcast(string|array $channels, string $event, array $data): void
    {
        $channelList = is_array($channels) ? implode(', ', $channels) : $channels;

        $this->logger->info('Broadcasting event', [
            'channels' => $channelList,
            'event' => $event,
            'data' => $data,
        ]);
    }
}

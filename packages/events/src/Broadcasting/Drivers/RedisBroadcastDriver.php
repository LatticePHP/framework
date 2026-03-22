<?php

declare(strict_types=1);

namespace Lattice\Events\Broadcasting\Drivers;

use Lattice\Events\Broadcasting\BroadcastDriverInterface;
use RuntimeException;

/**
 * Broadcast driver that publishes events to Redis pub/sub channels.
 *
 * Requires the ext-redis PHP extension. Each broadcast publishes a JSON
 * payload to every specified channel via Redis PUBLISH.
 */
final class RedisBroadcastDriver implements BroadcastDriverInterface
{
    private readonly \Redis $redis;

    /**
     * @param string $host  Redis host
     * @param int    $port  Redis port
     * @param string $prefix Optional channel name prefix
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        private readonly string $prefix = '',
    ) {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('The ext-redis PHP extension is required for RedisBroadcastDriver.');
        }

        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
    }

    public function broadcast(string|array $channels, string $event, array $data): void
    {
        $channels = is_array($channels) ? $channels : [$channels];

        $payload = json_encode([
            'event' => $event,
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        foreach ($channels as $channel) {
            $this->redis->publish($this->prefix . $channel, $payload);
        }
    }
}

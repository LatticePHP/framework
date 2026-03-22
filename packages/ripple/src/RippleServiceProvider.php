<?php

declare(strict_types=1);

namespace Lattice\Ripple;

use Lattice\Ripple\Auth\ChannelAuthenticator;
use Lattice\Ripple\Broadcasting\Broadcaster;
use Lattice\Ripple\Channel\ChannelManager;
use Lattice\Ripple\Console\RippleChannelsCommand;
use Lattice\Ripple\Console\RippleConnectionsCommand;
use Lattice\Ripple\Console\RippleServeCommand;
use Lattice\Ripple\Server\ConnectionManager;
use Lattice\Ripple\Server\WebSocketServer;

/**
 * Service provider for the Ripple WebSocket package.
 *
 * Registers configuration, binds core services, and registers CLI commands.
 */
final class RippleServiceProvider
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults(), $config);
    }

    /**
     * Get the registered commands.
     *
     * @return array<class-string>
     */
    public function getCommands(): array
    {
        return [
            RippleServeCommand::class,
            RippleConnectionsCommand::class,
            RippleChannelsCommand::class,
        ];
    }

    /**
     * Create a configured WebSocket server instance.
     */
    public function createServer(): WebSocketServer
    {
        return new WebSocketServer(
            host: (string) ($this->config['server']['host'] ?? '0.0.0.0'),
            port: (int) ($this->config['server']['port'] ?? 6001),
            maxConnections: (int) ($this->config['server']['max_connections'] ?? 10000),
            heartbeatInterval: (int) ($this->config['server']['heartbeat_interval'] ?? 25),
            maxPayloadSize: (int) ($this->config['server']['message_max_size'] ?? 65536),
        );
    }

    /**
     * Create a configured channel authenticator.
     */
    public function createAuthenticator(): ChannelAuthenticator
    {
        return new ChannelAuthenticator(
            appSecret: (string) ($this->config['auth']['secret'] ?? ''),
        );
    }

    /**
     * Get the configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Default configuration values.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'server' => [
                'host' => '0.0.0.0',
                'port' => 6001,
                'max_connections' => 10000,
                'heartbeat_interval' => 25,
                'message_max_size' => 65536,
                'allowed_origins' => ['*'],
            ],
            'auth' => [
                'endpoint' => '/api/broadcasting/auth',
                'guard' => 'default',
                'secret' => '',
            ],
            'ssl' => [
                'enabled' => false,
                'cert' => '',
                'key' => '',
            ],
            'redis' => [
                'connection' => 'default',
                'prefix' => 'ripple:',
            ],
            'rate_limiting' => [
                'messages_per_second' => 100,
                'connections_per_ip' => 100,
            ],
        ];
    }
}

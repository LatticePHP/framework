<?php

declare(strict_types=1);

namespace Lattice\Ripple\Server;

use Lattice\Ripple\Broadcasting\Broadcaster;
use Lattice\Ripple\Channel\ChannelManager;
use RuntimeException;
use Socket;

/**
 * Main WebSocket server using ext-sockets.
 *
 * Creates a TCP listener, accepts connections, performs the WebSocket
 * handshake, reads frames, and dispatches messages through the handler
 * pipeline. Implements graceful shutdown and heartbeat/ping-pong.
 */
final class WebSocketServer
{
    private ?Socket $serverSocket = null;
    private bool $running = false;

    private readonly ConnectionManager $connectionManager;
    private readonly ChannelManager $channelManager;
    private readonly Broadcaster $broadcaster;
    private readonly MessageHandler $messageHandler;

    private float $lastHeartbeat = 0.0;

    /** @var callable|null */
    private $onConnect = null;

    /** @var callable|null */
    private $onDisconnect = null;

    /** @var callable|null */
    private $onMessage = null;

    /** @var callable|null */
    private $onError = null;

    public function __construct(
        private readonly string $host = '0.0.0.0',
        private readonly int $port = 6001,
        int $maxConnections = 10000,
        int $maxConnectionsPerIp = 100,
        int $heartbeatInterval = 25,
        private readonly int $maxPayloadSize = 65536,
    ) {
        $this->connectionManager = new ConnectionManager(
            maxConnections: $maxConnections,
            maxConnectionsPerIp: $maxConnectionsPerIp,
            heartbeatInterval: $heartbeatInterval,
        );
        $this->channelManager = new ChannelManager();
        $this->broadcaster = new Broadcaster($this->connectionManager);
        $this->messageHandler = new MessageHandler(
            $this->channelManager,
            $this->broadcaster,
            $this->connectionManager,
        );
    }

    public function getConnectionManager(): ConnectionManager
    {
        return $this->connectionManager;
    }

    public function getChannelManager(): ChannelManager
    {
        return $this->channelManager;
    }

    public function getBroadcaster(): Broadcaster
    {
        return $this->broadcaster;
    }

    /**
     * Register a callback for new connections.
     */
    public function onConnect(callable $callback): void
    {
        $this->onConnect = $callback;
    }

    /**
     * Register a callback for disconnections.
     */
    public function onDisconnect(callable $callback): void
    {
        $this->onDisconnect = $callback;
    }

    /**
     * Register a callback for incoming messages.
     */
    public function onMessage(callable $callback): void
    {
        $this->onMessage = $callback;
    }

    /**
     * Register a callback for errors.
     */
    public function onError(callable $callback): void
    {
        $this->onError = $callback;
    }

    /**
     * Start the WebSocket server.
     *
     * @throws RuntimeException If the server socket cannot be created or bound.
     */
    public function start(): void
    {
        $this->serverSocket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($this->serverSocket === false) {
            throw new RuntimeException('Failed to create server socket: ' . socket_strerror(socket_last_error()));
        }

        socket_set_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (@socket_bind($this->serverSocket, $this->host, $this->port) === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to bind to %s:%d: %s',
                    $this->host,
                    $this->port,
                    socket_strerror(socket_last_error($this->serverSocket)),
                ),
            );
        }

        if (@socket_listen($this->serverSocket, 128) === false) {
            throw new RuntimeException(
                'Failed to listen: ' . socket_strerror(socket_last_error($this->serverSocket)),
            );
        }

        socket_set_nonblock($this->serverSocket);

        $this->running = true;
        $this->lastHeartbeat = microtime(true);

        $this->eventLoop();
    }

    /**
     * Stop the server gracefully.
     */
    public function stop(): void
    {
        $this->running = false;

        foreach ($this->connectionManager->getAllConnections() as $id => $connection) {
            $this->connectionManager->close($id, Frame::CLOSE_GOING_AWAY, 'Server shutting down');
        }

        if ($this->serverSocket !== null) {
            @socket_close($this->serverSocket);
            $this->serverSocket = null;
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * The main event loop using socket_select.
     */
    private function eventLoop(): void
    {
        while ($this->running) {
            $readSockets = [$this->serverSocket];

            foreach ($this->connectionManager->getAllConnections() as $connection) {
                $readSockets[] = $connection->socket;
            }

            $write = null;
            $except = null;

            $changed = @socket_select($readSockets, $write, $except, 0, 200000);

            if ($changed === false) {
                break;
            }

            if ($changed > 0) {
                foreach ($readSockets as $socket) {
                    if ($socket === $this->serverSocket) {
                        $this->acceptNewConnection();
                    } else {
                        $this->readFromConnection($socket);
                    }
                }
            }

            $this->checkHeartbeats();
        }
    }

    private function acceptNewConnection(): void
    {
        $clientSocket = @socket_accept($this->serverSocket);

        if ($clientSocket === false) {
            return;
        }

        socket_set_nonblock($clientSocket);

        $address = '';
        $port = 0;
        @socket_getpeername($clientSocket, $address, $port);

        try {
            $connection = $this->connectionManager->accept($clientSocket, $address, $port);
        } catch (RuntimeException $e) {
            $response = Handshake::buildErrorResponse(503, $e->getMessage());
            @socket_write($clientSocket, $response, strlen($response));
            @socket_close($clientSocket);

            return;
        }

        $data = @socket_read($clientSocket, 4096);

        if ($data === false || $data === '') {
            $this->connectionManager->forceClose($connection->id);

            return;
        }

        try {
            $request = Handshake::parseRequest($data);
            Handshake::validate($request['headers']);
            $response = Handshake::buildResponse($request['headers']);
            $this->connectionManager->sendRaw($connection, $response);
        } catch (RuntimeException $e) {
            $errorResponse = Handshake::buildErrorResponse(400, $e->getMessage());
            $this->connectionManager->sendRaw($connection, $errorResponse);
            $this->connectionManager->forceClose($connection->id);

            return;
        }

        if ($this->onConnect !== null) {
            ($this->onConnect)($connection);
        }
    }

    private function readFromConnection(Socket $socket): void
    {
        $connection = $this->findConnectionBySocket($socket);

        if ($connection === null) {
            return;
        }

        $data = @socket_read($socket, 65536, PHP_BINARY_READ);

        if ($data === false || $data === '') {
            $this->disconnectConnection($connection);

            return;
        }

        $connection->addBytesReceived(strlen($data));
        $connection->appendToBuffer($data);
        $connection->updateLastActivity();

        $this->processBuffer($connection);
    }

    private function processBuffer(Connection $connection): void
    {
        while (strlen($connection->getBuffer()) >= 2) {
            try {
                [$frame, $consumed] = Frame::decode($connection->getBuffer(), $this->maxPayloadSize);
            } catch (RuntimeException) {
                break;
            } catch (\InvalidArgumentException $e) {
                if (str_contains($e->getMessage(), 'Reserved opcode')) {
                    $this->connectionManager->close($connection->id, Frame::CLOSE_PROTOCOL_ERROR, 'Reserved opcode');
                } elseif (str_contains($e->getMessage(), 'exceeds maximum')) {
                    $this->connectionManager->close($connection->id, Frame::CLOSE_MESSAGE_TOO_BIG, 'Message too big');
                }

                return;
            }

            $connection->consumeBuffer($consumed);

            $this->handleFrame($connection, $frame);
        }
    }

    private function handleFrame(Connection $connection, Frame $frame): void
    {
        match ($frame->opcode) {
            Frame::OPCODE_TEXT => $this->handleTextFrame($connection, $frame),
            Frame::OPCODE_BINARY => $this->handleTextFrame($connection, $frame),
            Frame::OPCODE_PING => $this->handlePing($connection, $frame),
            Frame::OPCODE_PONG => $this->handlePong($connection),
            Frame::OPCODE_CLOSE => $this->handleCloseFrame($connection, $frame),
            default => null,
        };
    }

    private function handleTextFrame(Connection $connection, Frame $frame): void
    {
        $connection->incrementMessagesReceived();

        if ($this->onMessage !== null) {
            ($this->onMessage)($connection, $frame->payload);
        }

        $response = $this->messageHandler->handle($connection, $frame->payload);

        if ($response !== null) {
            $json = json_encode($response, JSON_THROW_ON_ERROR);
            $this->connectionManager->sendText($connection, $json);
        }
    }

    private function handlePing(Connection $connection, Frame $frame): void
    {
        $pong = Frame::pong($frame->payload);
        $this->connectionManager->sendRaw($connection, $pong);
    }

    private function handlePong(Connection $connection): void
    {
        $connection->updateLastActivity();
    }

    private function handleCloseFrame(Connection $connection, Frame $frame): void
    {
        [$code, $reason] = Frame::parseClosePayload($frame->payload);

        $closeResponse = Frame::close($code, $reason);
        $this->connectionManager->sendRaw($connection, $closeResponse);

        $this->disconnectConnection($connection);
    }

    private function disconnectConnection(Connection $connection): void
    {
        $channels = $connection->getChannels();

        foreach ($channels as $channel) {
            if (str_starts_with($channel, 'presence-')) {
                $member = $this->channelManager->getPresenceMember($channel, $connection->id);
                $this->channelManager->unsubscribePresence($connection->id, $channel);

                if ($member !== null) {
                    $this->broadcaster->broadcastToChannelExcept(
                        $channel,
                        'member_removed',
                        ['user_id' => $member['user_id'], 'user_info' => $member['user_info']],
                        $connection->id,
                    );
                }
            } else {
                $this->channelManager->unsubscribe($connection->id, $channel);
            }
        }

        if ($this->onDisconnect !== null) {
            ($this->onDisconnect)($connection);
        }

        $this->connectionManager->forceClose($connection->id);
    }

    private function findConnectionBySocket(Socket $socket): ?Connection
    {
        foreach ($this->connectionManager->getAllConnections() as $connection) {
            if ($connection->socket === $socket) {
                return $connection;
            }
        }

        return null;
    }

    private function checkHeartbeats(): void
    {
        $now = microtime(true);

        if ($now - $this->lastHeartbeat < $this->connectionManager->getHeartbeatInterval()) {
            return;
        }

        $this->lastHeartbeat = $now;

        $deadConnections = $this->connectionManager->heartbeat();

        foreach ($deadConnections as $connectionId) {
            $connection = $this->connectionManager->getConnection($connectionId);

            if ($connection !== null) {
                $this->disconnectConnection($connection);
            }
        }
    }
}

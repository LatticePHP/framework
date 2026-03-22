<?php

declare(strict_types=1);

namespace Lattice\Transport\RabbitMq;

final class RabbitMqConfig
{
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 5672,
        public readonly string $user = 'guest',
        public readonly string $password = 'guest',
        public readonly string $vhost = '/',
        public readonly string $exchange = '',
        public readonly string $queue = '',
        public readonly string $routingKey = '',
    ) {}

    public function getDsn(): string
    {
        return sprintf(
            'amqp://%s:%s@%s:%d/%s',
            $this->user,
            $this->password,
            $this->host,
            $this->port,
            ltrim($this->vhost, '/'),
        );
    }
}

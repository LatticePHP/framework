<?php

declare(strict_types=1);

namespace Lattice\Transport\Nats;

final class NatsConfig
{
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 4222,
        public readonly ?string $user = null,
        public readonly ?string $password = null,
        public readonly bool $tls = false,
    ) {}

    public function getDsn(): string
    {
        $scheme = $this->tls ? 'tls' : 'nats';
        $auth = '';

        if ($this->user !== null) {
            $auth = $this->password !== null
                ? sprintf('%s:%s@', $this->user, $this->password)
                : sprintf('%s@', $this->user);
        }

        return sprintf('%s://%s%s:%d', $scheme, $auth, $this->host, $this->port);
    }
}

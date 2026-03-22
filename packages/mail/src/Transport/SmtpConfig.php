<?php

declare(strict_types=1);

namespace Lattice\Mail\Transport;

final readonly class SmtpConfig
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 587,
        public ?string $username = null,
        public ?string $password = null,
        public string $encryption = 'tls',
    ) {}
}

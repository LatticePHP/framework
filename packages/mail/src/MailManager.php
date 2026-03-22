<?php

declare(strict_types=1);

namespace Lattice\Mail;

use Lattice\Mail\Transport\MailTransportInterface;

final class MailManager
{
    public function __construct(
        private readonly MailTransportInterface $transport,
    ) {}

    public function send(Mailable $mailable): void
    {
        $this->transport->send($mailable);
    }

    public function queue(Mailable $mailable, ?string $queue = null): void
    {
        // In a full implementation, this would dispatch to a queue system.
        // For now, we send synchronously as a baseline.
        $this->transport->send($mailable);
    }
}

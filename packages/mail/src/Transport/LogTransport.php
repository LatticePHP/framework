<?php

declare(strict_types=1);

namespace Lattice\Mail\Transport;

use Lattice\Mail\Mailable;

final class LogTransport implements MailTransportInterface
{
    /** @var list<string> */
    private array $logs = [];

    public function send(Mailable $mailable): void
    {
        $mailable->build();

        $from = $mailable->getFrom();
        $to = implode(', ', $mailable->getTo());
        $subject = $mailable->getSubject();

        $this->logs[] = sprintf(
            '[MAIL] From: %s | To: %s | Subject: %s',
            $from['address'],
            $to,
            $subject,
        );
    }

    /** @return list<string> */
    public function getLogs(): array
    {
        return $this->logs;
    }
}

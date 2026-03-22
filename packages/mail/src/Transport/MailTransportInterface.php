<?php

declare(strict_types=1);

namespace Lattice\Mail\Transport;

use Lattice\Mail\Mailable;

interface MailTransportInterface
{
    public function send(Mailable $mailable): void;
}

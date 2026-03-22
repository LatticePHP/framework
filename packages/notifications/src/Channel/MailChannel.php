<?php

declare(strict_types=1);

namespace Lattice\Notifications\Channel;

use Lattice\Mail\MailManager;
use Lattice\Mail\Mailable;
use Lattice\Notifications\Notification;
use RuntimeException;

final class MailChannel implements ChannelInterface
{
    public function __construct(
        private readonly MailManager $mailManager,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $mailable = $notification->toMail($notifiable);

        if (!$mailable instanceof Mailable) {
            throw new RuntimeException(
                sprintf('Notification [%s] did not return a Mailable from toMail().', $notification::class),
            );
        }

        $this->mailManager->send($mailable);
    }
}

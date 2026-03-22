<?php

declare(strict_types=1);

namespace Lattice\Notifications\Channel;

use Lattice\Notifications\Notification;

interface ChannelInterface
{
    public function send(object $notifiable, Notification $notification): void;
}

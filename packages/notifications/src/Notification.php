<?php

declare(strict_types=1);

namespace Lattice\Notifications;

abstract class Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return string[]
     */
    abstract public function via(object $notifiable): array;

    /**
     * Get the mail representation of the notification.
     *
     * @return \Lattice\Mail\Mailable|null
     */
    public function toMail(object $notifiable): mixed
    {
        return null;
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>|null
     */
    public function toDatabase(object $notifiable): ?array
    {
        return null;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}

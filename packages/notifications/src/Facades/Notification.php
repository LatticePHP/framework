<?php

declare(strict_types=1);

namespace Lattice\Notifications\Facades;

use Lattice\Notifications\Notification as BaseNotification;
use Lattice\Notifications\NotificationManager;
use Lattice\Notifications\Testing\FakeNotificationChannel;

final class Notification
{
    private static ?NotificationManager $instance = null;

    /**
     * Set the NotificationManager instance used by the facade.
     */
    public static function setInstance(NotificationManager $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get the NotificationManager instance, resolving from the container if not set.
     */
    public static function getInstance(): NotificationManager
    {
        if (self::$instance === null) {
            self::$instance = \app(NotificationManager::class);
        }

        return self::$instance;
    }

    /**
     * Send the given notification to the given notifiable entity.
     */
    public static function send(object $notifiable, BaseNotification $notification): void
    {
        self::getInstance()->send($notifiable, $notification);
    }

    /**
     * Replace all channels with a fake channel for testing.
     */
    public static function fake(): FakeNotificationChannel
    {
        $fake = new FakeNotificationChannel();
        $manager = new NotificationManager();
        $manager->registerChannel('fake', $fake);
        self::$instance = $manager;

        return $fake;
    }

    /**
     * Reset the facade instance (useful in tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

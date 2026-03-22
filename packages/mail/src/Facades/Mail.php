<?php

declare(strict_types=1);

namespace Lattice\Mail\Facades;

use Lattice\Mail\Mailable;
use Lattice\Mail\MailManager;
use Lattice\Mail\PendingMail;
use Lattice\Mail\Transport\InMemoryTransport;

final class Mail
{
    private static ?MailManager $instance = null;

    /**
     * Set the MailManager instance used by the facade.
     */
    public static function setInstance(MailManager $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get the MailManager instance, resolving from the container if not set.
     */
    public static function getInstance(): MailManager
    {
        if (self::$instance === null) {
            self::$instance = \app(MailManager::class);
        }

        return self::$instance;
    }

    /**
     * Begin building a mail to the given recipients.
     *
     * @param string|string[] $address
     */
    public static function to(string|array $address): PendingMail
    {
        return new PendingMail(self::getInstance(), $address);
    }

    /**
     * Send the given mailable immediately.
     */
    public static function send(Mailable $mailable): void
    {
        self::getInstance()->send($mailable);
    }

    /**
     * Queue the given mailable for later sending.
     */
    public static function queue(Mailable $mailable, ?string $queue = null): void
    {
        self::getInstance()->queue($mailable, $queue);
    }

    /**
     * Replace the mail transport with an in-memory fake for testing.
     */
    public static function fake(): InMemoryTransport
    {
        $fake = new InMemoryTransport();
        self::$instance = new MailManager($fake);

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

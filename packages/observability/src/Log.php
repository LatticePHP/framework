<?php

declare(strict_types=1);

namespace Lattice\Observability;

use Lattice\Observability\Logger\StreamLogHandler;
use Lattice\Observability\Logger\StructuredLogger;

final class Log
{
    private static ?StructuredLogger $instance = null;

    public static function setInstance(StructuredLogger $logger): void
    {
        self::$instance = $logger;
    }

    public static function getInstance(): StructuredLogger
    {
        if (self::$instance === null) {
            // Default: stderr handler (Docker-friendly)
            $handler = new StreamLogHandler('php://stderr');
            self::$instance = new StructuredLogger($handler);
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }
}

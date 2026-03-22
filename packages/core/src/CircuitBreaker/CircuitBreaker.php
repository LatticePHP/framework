<?php

declare(strict_types=1);

namespace Lattice\Core\CircuitBreaker;

final class CircuitBreaker
{
    /** @var array<string, Circuit> */
    private static array $circuits = [];

    /** @var array<string, array{failureThreshold?: int, successThreshold?: int, timeout?: int}> */
    private static array $configs = [];

    public static function call(string $service, callable $action, ?callable $fallback = null): mixed
    {
        $circuit = self::getCircuit($service);

        if ($circuit->isOpen()) {
            if ($fallback !== null) {
                return $fallback();
            }

            throw new CircuitOpenException($service);
        }

        try {
            $result = $action();
            $circuit->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $circuit->recordFailure();

            if ($circuit->isOpen() && $fallback !== null) {
                return $fallback();
            }

            throw $e;
        }
    }

    /**
     * @param array{failureThreshold?: int, successThreshold?: int, timeout?: int} $config
     */
    public static function configure(string $service, array $config): void
    {
        self::$configs[$service] = $config;

        // If circuit already exists, recreate with new config
        if (isset(self::$circuits[$service])) {
            unset(self::$circuits[$service]);
        }
    }

    public static function getState(string $service): string
    {
        return self::getCircuit($service)->getState();
    }

    public static function reset(string $service): void
    {
        if (isset(self::$circuits[$service])) {
            self::$circuits[$service]->reset();
        }
    }

    public static function resetAll(): void
    {
        self::$circuits = [];
        self::$configs = [];
    }

    private static function getCircuit(string $service): Circuit
    {
        if (!isset(self::$circuits[$service])) {
            $config = self::$configs[$service] ?? [];

            self::$circuits[$service] = new Circuit(
                failureThreshold: $config['failureThreshold'] ?? 5,
                successThreshold: $config['successThreshold'] ?? 2,
                timeout: $config['timeout'] ?? 30,
            );
        }

        return self::$circuits[$service];
    }
}

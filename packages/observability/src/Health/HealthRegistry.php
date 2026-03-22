<?php

declare(strict_types=1);

namespace Lattice\Observability\Health;

final class HealthRegistry
{
    /** @var HealthCheckInterface[] */
    private array $checks = [];

    public function register(HealthCheckInterface $check): void
    {
        $this->checks[$check->getName()] = $check;
    }

    /**
     * @return array<string, HealthResult>
     */
    public function checkAll(): array
    {
        $results = [];

        foreach ($this->checks as $name => $check) {
            $start = microtime(true);
            try {
                $result = $check->check();
                $duration = microtime(true) - $start;
                $results[$name] = new HealthResult(
                    status: $result->status,
                    message: $result->message,
                    metadata: $result->metadata,
                    duration: $duration,
                );
            } catch (\Throwable $e) {
                $duration = microtime(true) - $start;
                $results[$name] = new HealthResult(
                    status: HealthStatus::Down,
                    message: $e->getMessage(),
                    duration: $duration,
                );
            }
        }

        return $results;
    }

    public function toArray(): array
    {
        $results = $this->checkAll();
        $overall = HealthStatus::Up;

        $checks = [];
        foreach ($results as $name => $result) {
            $checks[$name] = $result->toArray();

            if ($result->status === HealthStatus::Down) {
                $overall = HealthStatus::Down;
            } elseif ($result->status === HealthStatus::Degraded && $overall !== HealthStatus::Down) {
                $overall = HealthStatus::Degraded;
            }
        }

        return [
            'status' => $overall->value,
            'checks' => $checks,
        ];
    }
}

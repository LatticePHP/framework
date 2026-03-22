<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

final class SystemDetector
{
    /** @var list<DetectorInterface> */
    private array $detectors;

    /**
     * @param list<DetectorInterface>|null $detectors
     */
    public function __construct(?array $detectors = null)
    {
        $this->detectors = $detectors ?? $this->defaultDetectors();
    }

    /**
     * Run all detectors and return their results.
     *
     * @return list<DetectionResult>
     */
    public function detectAll(): array
    {
        $results = [];

        foreach ($this->detectors as $detector) {
            $results[] = $detector->detect();
        }

        return $results;
    }

    /**
     * Add a custom detector.
     */
    public function addDetector(DetectorInterface $detector): void
    {
        $this->detectors[] = $detector;
    }

    /**
     * @return list<DetectorInterface>
     */
    private function defaultDetectors(): array
    {
        return [
            new NginxDetector(),
            new PhpDetector(),
            new NodeDetector(),
            new RedisDetector(),
            new MysqlDetector(),
            new PostgresDetector(),
            new DockerDetector(),
        ];
    }
}

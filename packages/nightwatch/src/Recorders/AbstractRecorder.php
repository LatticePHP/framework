<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Recorders;

abstract class AbstractRecorder implements RecorderInterface
{
    protected bool $enabled = true;
    private readonly float $samplingRate;

    public function __construct(float $samplingRate = 1.0)
    {
        $this->samplingRate = max(0.0, min(1.0, $samplingRate));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    protected function shouldSample(): bool
    {
        if ($this->samplingRate >= 1.0) {
            return true;
        }

        if ($this->samplingRate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= $this->samplingRate;
    }
}

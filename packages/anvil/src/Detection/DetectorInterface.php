<?php

declare(strict_types=1);

namespace Lattice\Anvil\Detection;

interface DetectorInterface
{
    /**
     * Detect the presence and status of a system service.
     */
    public function detect(): DetectionResult;
}

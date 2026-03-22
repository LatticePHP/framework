<?php

declare(strict_types=1);

namespace Lattice\Prism\Fingerprint;

use Lattice\Prism\Event\ErrorEvent;

final class Fingerprinter
{
    private readonly StacktraceNormalizer $normalizer;

    public function __construct(?StacktraceNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new StacktraceNormalizer();
    }

    /**
     * Generate a SHA-256 fingerprint for an error event.
     *
     * Strategy:
     * 1. If custom fingerprint is provided, use it directly.
     * 2. If stacktrace is present, use exception type + normalized frames.
     * 3. If no stacktrace, use exception type + message.
     * 4. If no exception, use level + transaction + message.
     */
    public function generate(ErrorEvent $event): string
    {
        // Custom fingerprint override
        if ($event->fingerprint !== null && $event->fingerprint !== []) {
            $input = implode('|', $event->fingerprint);

            return hash('sha256', $input);
        }

        // Stacktrace-based fingerprint
        if ($event->stacktrace !== []) {
            $normalizedFrames = $this->normalizer->normalize($event->stacktrace);

            $parts = [$event->exceptionType ?? ''];

            foreach ($normalizedFrames as $frame) {
                $parts[] = $frame['class'] . ':' . $frame['function'];
            }

            $input = implode('|', $parts);

            return hash('sha256', $input);
        }

        // Exception without stacktrace
        if ($event->exceptionType !== null) {
            $input = ($event->exceptionType ?? '') . '|' . ($event->exceptionMessage ?? '');

            return hash('sha256', $input);
        }

        // No exception at all — use level + transaction + message
        $input = $event->level->value . '|' . ($event->transaction ?? '') . '|' . ($event->exceptionMessage ?? '');

        return hash('sha256', $input);
    }
}

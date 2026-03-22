<?php

declare(strict_types=1);

namespace Lattice\Prism\Fingerprint;

use Lattice\Prism\Event\StackFrame;

final class StacktraceNormalizer
{
    /** @var list<string> */
    private readonly array $vendorPrefixes;

    /**
     * @param list<string>|null $vendorPrefixes
     */
    public function __construct(?array $vendorPrefixes = null)
    {
        $this->vendorPrefixes = $vendorPrefixes ?? [
            'vendor/',
            'vendor\\',
            'node_modules/',
            'node_modules\\',
        ];
    }

    /**
     * Normalize a stacktrace for fingerprinting: strip line numbers, vendor frames, and absolute paths.
     *
     * @param list<StackFrame> $frames
     * @return list<array{class: string, function: string}>
     */
    public function normalize(array $frames): array
    {
        $normalized = [];

        foreach ($frames as $frame) {
            if ($this->isVendorFrame($frame)) {
                continue;
            }

            $normalized[] = [
                'class' => $frame->class ?? '',
                'function' => $frame->function ?? '',
            ];
        }

        return $normalized;
    }

    /**
     * Normalize a file path: strip absolute parts, keep relative.
     */
    public function normalizePath(string $path): string
    {
        // Normalize directory separators
        $path = str_replace('\\', '/', $path);

        // Strip common absolute path prefixes
        $patterns = [
            // Unix: /home/user/project/src/Foo.php -> src/Foo.php
            '#^/(?:home|var|opt|srv|Users)/[^/]+/[^/]+/#',
            // Windows: C:\Users\user\project\src\Foo.php -> src/Foo.php
            '#^[A-Za-z]:/Users/[^/]+/[^/]+/#',
            // Docker/container: /app/src/Foo.php -> src/Foo.php
            '#^/app/#',
            // Webpack: webpack:///src/Foo.js -> src/Foo.js
            '#^webpack:///+#',
        ];

        foreach ($patterns as $pattern) {
            $path = preg_replace($pattern, '', $path) ?? $path;
        }

        return $path;
    }

    private function isVendorFrame(StackFrame $frame): bool
    {
        $file = str_replace('\\', '/', $frame->file);

        foreach ($this->vendorPrefixes as $prefix) {
            $normalizedPrefix = str_replace('\\', '/', $prefix);
            if (str_contains($file, $normalizedPrefix)) {
                return true;
            }
        }

        return false;
    }
}

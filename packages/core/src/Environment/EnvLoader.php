<?php

declare(strict_types=1);

namespace Lattice\Core\Environment;

final class EnvLoader
{
    /**
     * Parse .env content string into key-value pairs.
     *
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $result = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain = to be a valid entry
            if (!str_contains($line, '=')) {
                continue;
            }

            // Split on first = only
            $pos = strpos($line, '=');
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Handle quoted values
            if (self::isQuoted($value, '"') || self::isQuoted($value, "'")) {
                $value = substr($value, 1, -1);
            } else {
                // Strip inline comments (only for unquoted values)
                $commentPos = strpos($value, ' #');
                if ($commentPos !== false) {
                    $value = trim(substr($value, 0, $commentPos));
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Load and parse a .env file.
     *
     * @return array<string, string>
     */
    public static function loadFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        return self::parse($content);
    }

    private static function isQuoted(string $value, string $quote): bool
    {
        return strlen($value) >= 2
            && str_starts_with($value, $quote)
            && str_ends_with($value, $quote);
    }
}

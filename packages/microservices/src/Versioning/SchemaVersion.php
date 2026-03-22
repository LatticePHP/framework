<?php

declare(strict_types=1);

namespace Lattice\Microservices\Versioning;

use InvalidArgumentException;
use Stringable;

final readonly class SchemaVersion implements Stringable
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {}

    public static function parse(string $version): self
    {
        $parts = explode('.', $version);

        if (count($parts) < 2 || count($parts) > 3) {
            throw new InvalidArgumentException(
                sprintf('Invalid schema version format: "%s". Expected semver (e.g., "1.2.3" or "1.2").', $version),
            );
        }

        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid schema version format: "%s". Each segment must be numeric.', $version),
                );
            }
        }

        return new self(
            major: (int) $parts[0],
            minor: (int) $parts[1],
            patch: (int) ($parts[2] ?? 0),
        );
    }

    /**
     * Two versions are compatible if they share the same major version.
     */
    public function isCompatible(SchemaVersion $other): bool
    {
        return $this->major === $other->major;
    }

    public function __toString(): string
    {
        return sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
    }
}

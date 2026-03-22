<?php

declare(strict_types=1);

namespace Lattice\Microservices\Tests\Unit;

use InvalidArgumentException;
use Lattice\Microservices\Versioning\SchemaVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaVersionTest extends TestCase
{
    #[Test]
    public function it_parses_semver_string(): void
    {
        $version = SchemaVersion::parse('2.3.1');

        $this->assertSame(2, $version->major);
        $this->assertSame(3, $version->minor);
        $this->assertSame(1, $version->patch);
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $version = SchemaVersion::parse('1.0.5');

        $this->assertSame('1.0.5', (string) $version);
    }

    #[Test]
    public function it_throws_for_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SchemaVersion::parse('not-a-version');
    }

    #[Test]
    public function it_considers_same_major_compatible(): void
    {
        $v1 = SchemaVersion::parse('2.0.0');
        $v2 = SchemaVersion::parse('2.5.3');

        $this->assertTrue($v1->isCompatible($v2));
        $this->assertTrue($v2->isCompatible($v1));
    }

    #[Test]
    public function it_considers_different_major_incompatible(): void
    {
        $v1 = SchemaVersion::parse('1.0.0');
        $v2 = SchemaVersion::parse('2.0.0');

        $this->assertFalse($v1->isCompatible($v2));
        $this->assertFalse($v2->isCompatible($v1));
    }

    #[Test]
    public function it_considers_exact_match_compatible(): void
    {
        $v1 = SchemaVersion::parse('3.2.1');
        $v2 = SchemaVersion::parse('3.2.1');

        $this->assertTrue($v1->isCompatible($v2));
    }

    #[Test]
    public function it_parses_two_segment_version(): void
    {
        $version = SchemaVersion::parse('1.2');

        $this->assertSame(1, $version->major);
        $this->assertSame(2, $version->minor);
        $this->assertSame(0, $version->patch);
    }
}

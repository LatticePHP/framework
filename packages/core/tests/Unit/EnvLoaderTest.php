<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Environment\EnvLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvLoaderTest extends TestCase
{
    #[Test]
    public function parse_simple_key_value(): void
    {
        $result = EnvLoader::parse("APP_NAME=Lattice\nAPP_DEBUG=true");

        $this->assertSame('Lattice', $result['APP_NAME']);
        $this->assertSame('true', $result['APP_DEBUG']);
    }

    #[Test]
    public function parse_ignores_comments(): void
    {
        $content = <<<'ENV'
        # This is a comment
        APP_NAME=Lattice
        # Another comment
        APP_ENV=local
        ENV;

        $result = EnvLoader::parse($content);

        $this->assertArrayNotHasKey('# This is a comment', $result);
        $this->assertSame('Lattice', $result['APP_NAME']);
        $this->assertSame('local', $result['APP_ENV']);
    }

    #[Test]
    public function parse_ignores_empty_lines(): void
    {
        $content = "APP_NAME=Lattice\n\n\nAPP_ENV=local\n";

        $result = EnvLoader::parse($content);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function parse_double_quoted_values(): void
    {
        $result = EnvLoader::parse('APP_NAME="Lattice Framework"');

        $this->assertSame('Lattice Framework', $result['APP_NAME']);
    }

    #[Test]
    public function parse_single_quoted_values(): void
    {
        $result = EnvLoader::parse("APP_NAME='Lattice Framework'");

        $this->assertSame('Lattice Framework', $result['APP_NAME']);
    }

    #[Test]
    public function parse_trims_whitespace(): void
    {
        $result = EnvLoader::parse("  APP_NAME = Lattice  \n  APP_ENV = local  ");

        $this->assertSame('Lattice', $result['APP_NAME']);
        $this->assertSame('local', $result['APP_ENV']);
    }

    #[Test]
    public function parse_handles_empty_value(): void
    {
        $result = EnvLoader::parse('APP_SECRET=');

        $this->assertSame('', $result['APP_SECRET']);
    }

    #[Test]
    public function parse_handles_value_with_equals_sign(): void
    {
        $result = EnvLoader::parse('DATABASE_URL=mysql://user:pass@host/db?charset=utf8');

        $this->assertSame('mysql://user:pass@host/db?charset=utf8', $result['DATABASE_URL']);
    }

    #[Test]
    public function load_from_file(): void
    {
        $tmpFile = sys_get_temp_dir() . '/lattice-env-test-' . uniqid();
        file_put_contents($tmpFile, "APP_NAME=Lattice\nAPP_ENV=testing\n");

        $result = EnvLoader::loadFile($tmpFile);

        $this->assertSame('Lattice', $result['APP_NAME']);
        $this->assertSame('testing', $result['APP_ENV']);

        unlink($tmpFile);
    }

    #[Test]
    public function load_from_nonexistent_file_returns_empty(): void
    {
        $result = EnvLoader::loadFile('/nonexistent/path/.env');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_inline_comments_are_ignored(): void
    {
        $result = EnvLoader::parse('APP_NAME=Lattice # this is a comment');

        $this->assertSame('Lattice', $result['APP_NAME']);
    }

    #[Test]
    public function parse_quoted_values_preserve_hash(): void
    {
        $result = EnvLoader::parse('APP_SECRET="abc#123"');

        $this->assertSame('abc#123', $result['APP_SECRET']);
    }
}

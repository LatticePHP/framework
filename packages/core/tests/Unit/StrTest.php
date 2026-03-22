<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Support\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function test_studly(): void
    {
        $this->assertSame('FooBar', Str::studly('foo_bar'));
        $this->assertSame('FooBar', Str::studly('foo-bar'));
        $this->assertSame('FooBar', Str::studly('foo bar'));
        $this->assertSame('Foobar', Str::studly('foobar'));
    }

    public function test_camel(): void
    {
        $this->assertSame('fooBar', Str::camel('foo_bar'));
        $this->assertSame('fooBar', Str::camel('foo-bar'));
        $this->assertSame('fooBar', Str::camel('FooBar'));
    }

    public function test_snake(): void
    {
        $this->assertSame('foo_bar', Str::snake('FooBar'));
        $this->assertSame('foo_bar', Str::snake('fooBar'));
        $this->assertSame('foo_bar_baz', Str::snake('FooBarBaz'));
    }

    public function test_kebab(): void
    {
        $this->assertSame('foo-bar', Str::kebab('FooBar'));
        $this->assertSame('foo-bar', Str::kebab('fooBar'));
    }

    public function test_slug(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello World'));
        $this->assertSame('hello-world', Str::slug('Hello  World'));
        $this->assertSame('hello_world', Str::slug('Hello World', '_'));
        $this->assertSame('hello-world', Str::slug('Hello World!@#'));
    }

    public function test_starts_with(): void
    {
        $this->assertTrue(Str::startsWith('Hello World', 'Hello'));
        $this->assertFalse(Str::startsWith('Hello World', 'World'));
        $this->assertTrue(Str::startsWith('Hello World', ['Bye', 'Hello']));
        $this->assertFalse(Str::startsWith('Hello', ''));
    }

    public function test_ends_with(): void
    {
        $this->assertTrue(Str::endsWith('Hello World', 'World'));
        $this->assertFalse(Str::endsWith('Hello World', 'Hello'));
        $this->assertTrue(Str::endsWith('Hello World', ['Nope', 'World']));
        $this->assertFalse(Str::endsWith('Hello', ''));
    }

    public function test_contains(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'lo Wo'));
        $this->assertFalse(Str::contains('Hello World', 'xyz'));
        $this->assertTrue(Str::contains('Hello World', ['xyz', 'World']));
        $this->assertFalse(Str::contains('Hello', ''));
    }

    public function test_uuid(): void
    {
        $uuid = Str::uuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
    }

    public function test_uuid_uniqueness(): void
    {
        $uuid1 = Str::uuid();
        $uuid2 = Str::uuid();

        $this->assertNotSame($uuid1, $uuid2);
    }

    public function test_random(): void
    {
        $random = Str::random(32);
        $this->assertSame(32, strlen($random));
    }

    public function test_random_default_length(): void
    {
        $random = Str::random();
        $this->assertSame(16, strlen($random));
    }

    public function test_random_uniqueness(): void
    {
        $this->assertNotSame(Str::random(), Str::random());
    }

    public function test_upper(): void
    {
        $this->assertSame('HELLO', Str::upper('hello'));
        $this->assertSame('HELLO', Str::upper('Hello'));
    }

    public function test_lower(): void
    {
        $this->assertSame('hello', Str::lower('HELLO'));
        $this->assertSame('hello', Str::lower('Hello'));
    }

    public function test_title(): void
    {
        $this->assertSame('Hello World', Str::title('hello world'));
    }

    public function test_limit(): void
    {
        $this->assertSame('Hello...', Str::limit('Hello World', 5));
        $this->assertSame('Hello World', Str::limit('Hello World', 20));
        $this->assertSame('He--', Str::limit('Hello', 2, '--'));
    }

    public function test_plural(): void
    {
        $this->assertSame('dogs', Str::plural('dog'));
        $this->assertSame('buses', Str::plural('bus'));
        $this->assertSame('boxes', Str::plural('box'));
        $this->assertSame('churches', Str::plural('church'));
        $this->assertSame('flies', Str::plural('fly'));
        $this->assertSame('days', Str::plural('day'));
        $this->assertSame('cats', Str::plural('cats'));
        $this->assertSame('', Str::plural(''));
    }

    public function test_singular(): void
    {
        $this->assertSame('dog', Str::singular('dogs'));
        $this->assertSame('bus', Str::singular('buses'));
        $this->assertSame('box', Str::singular('boxes'));
        $this->assertSame('fly', Str::singular('flies'));
        $this->assertSame('class', Str::singular('class'));
        $this->assertSame('', Str::singular(''));
    }
}

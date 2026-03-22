<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Support\Arr;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    public function test_get_with_simple_key(): void
    {
        $this->assertSame('bar', Arr::get(['foo' => 'bar'], 'foo'));
    }

    public function test_get_with_dot_notation(): void
    {
        $array = ['user' => ['name' => 'John', 'address' => ['city' => 'NYC']]];

        $this->assertSame('John', Arr::get($array, 'user.name'));
        $this->assertSame('NYC', Arr::get($array, 'user.address.city'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertNull(Arr::get([], 'missing'));
        $this->assertSame('fallback', Arr::get([], 'missing', 'fallback'));
    }

    public function test_get_prefers_exact_key_match(): void
    {
        $array = ['user.name' => 'exact', 'user' => ['name' => 'nested']];

        $this->assertSame('exact', Arr::get($array, 'user.name'));
    }

    public function test_set_with_simple_key(): void
    {
        $array = [];
        Arr::set($array, 'foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $array);
    }

    public function test_set_with_dot_notation(): void
    {
        $array = [];
        Arr::set($array, 'user.name', 'John');

        $this->assertSame(['user' => ['name' => 'John']], $array);
    }

    public function test_set_overwrites_existing(): void
    {
        $array = ['user' => ['name' => 'Old']];
        Arr::set($array, 'user.name', 'New');

        $this->assertSame('New', $array['user']['name']);
    }

    public function test_has_with_simple_key(): void
    {
        $this->assertTrue(Arr::has(['foo' => 'bar'], 'foo'));
        $this->assertFalse(Arr::has(['foo' => 'bar'], 'baz'));
    }

    public function test_has_with_dot_notation(): void
    {
        $array = ['user' => ['name' => 'John']];

        $this->assertTrue(Arr::has($array, 'user.name'));
        $this->assertFalse(Arr::has($array, 'user.email'));
    }

    public function test_forget_simple_key(): void
    {
        $array = ['foo' => 'bar', 'baz' => 'qux'];
        Arr::forget($array, 'foo');

        $this->assertSame(['baz' => 'qux'], $array);
    }

    public function test_forget_dot_notation(): void
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        Arr::forget($array, 'user.name');

        $this->assertSame(['user' => ['age' => 30]], $array);
    }

    public function test_forget_missing_key_does_nothing(): void
    {
        $array = ['foo' => 'bar'];
        Arr::forget($array, 'missing.key');

        $this->assertSame(['foo' => 'bar'], $array);
    }

    public function test_flatten(): void
    {
        $this->assertSame([1, 2, 3, 4], Arr::flatten([[1, 2], [3, 4]]));
    }

    public function test_flatten_deep(): void
    {
        $this->assertSame([1, 2, 3], Arr::flatten([[1, [2, [3]]]]));
    }

    public function test_flatten_with_depth(): void
    {
        $this->assertSame([1, 2, [3]], Arr::flatten([[1, [2, [3]]]], 2));
    }

    public function test_only(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(['a' => 1, 'c' => 3], Arr::only($array, ['a', 'c']));
    }

    public function test_except(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(['a' => 1, 'c' => 3], Arr::except($array, ['b']));
    }

    public function test_first_without_callback(): void
    {
        $this->assertSame(1, Arr::first([1, 2, 3]));
        $this->assertNull(Arr::first([]));
        $this->assertSame('default', Arr::first([], null, 'default'));
    }

    public function test_first_with_callback(): void
    {
        $result = Arr::first([1, 2, 3, 4], fn (int $v): bool => $v > 2);

        $this->assertSame(3, $result);
    }

    public function test_first_returns_default_when_no_match(): void
    {
        $result = Arr::first([1, 2, 3], fn (int $v): bool => $v > 10, 'none');

        $this->assertSame('none', $result);
    }

    public function test_last_without_callback(): void
    {
        $this->assertSame(3, Arr::last([1, 2, 3]));
        $this->assertNull(Arr::last([]));
    }

    public function test_last_with_callback(): void
    {
        $result = Arr::last([1, 2, 3, 4], fn (int $v): bool => $v < 3);

        $this->assertSame(2, $result);
    }

    public function test_wrap(): void
    {
        $this->assertSame([1, 2], Arr::wrap([1, 2]));
        $this->assertSame(['hello'], Arr::wrap('hello'));
        $this->assertSame([], Arr::wrap(null));
        $this->assertSame([0], Arr::wrap(0));
    }

    public function test_pluck(): void
    {
        $array = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];

        $this->assertSame(['Alice', 'Bob'], Arr::pluck($array, 'name'));
    }

    public function test_pluck_with_objects(): void
    {
        $obj1 = new \stdClass();
        $obj1->name = 'Alice';
        $obj2 = new \stdClass();
        $obj2->name = 'Bob';

        $this->assertSame(['Alice', 'Bob'], Arr::pluck([$obj1, $obj2], 'name'));
    }

    public function test_pluck_skips_missing_keys(): void
    {
        $array = [
            ['name' => 'Alice'],
            ['age' => 25],
        ];

        $this->assertSame(['Alice'], Arr::pluck($array, 'name'));
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\Pagination\Paginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    #[Test]
    public function test_items_returns_provided_items(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 10, perPage: 2, currentPage: 1);

        $this->assertEquals($items, $paginator->items());
    }

    #[Test]
    public function test_total_returns_total_count(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 10, currentPage: 1);

        $this->assertEquals(50, $paginator->total());
    }

    #[Test]
    public function test_per_page_returns_per_page_value(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 15, currentPage: 1);

        $this->assertEquals(15, $paginator->perPage());
    }

    #[Test]
    public function test_current_page_returns_current_page(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 10, currentPage: 3);

        $this->assertEquals(3, $paginator->currentPage());
    }

    #[Test]
    public function test_last_page_calculated_correctly(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 10, currentPage: 1);
        $this->assertEquals(5, $paginator->lastPage());

        $paginator2 = new Paginator([], total: 51, perPage: 10, currentPage: 1);
        $this->assertEquals(6, $paginator2->lastPage());

        $paginator3 = new Paginator([], total: 0, perPage: 10, currentPage: 1);
        $this->assertEquals(1, $paginator3->lastPage());
    }

    #[Test]
    public function test_has_more_pages_returns_true_when_not_on_last_page(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 10, currentPage: 3);

        $this->assertTrue($paginator->hasMorePages());
    }

    #[Test]
    public function test_has_more_pages_returns_false_on_last_page(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 10, currentPage: 5);

        $this->assertFalse($paginator->hasMorePages());
    }

    #[Test]
    public function test_has_more_pages_returns_false_when_past_last_page(): void
    {
        $paginator = new Paginator([], total: 50, perPage: 10, currentPage: 10);

        $this->assertFalse($paginator->hasMorePages());
    }

    #[Test]
    public function test_to_array_returns_correct_structure(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 20, perPage: 2, currentPage: 3);

        $array = $paginator->toArray();

        $this->assertEquals($items, $array['data']);
        $this->assertEquals(20, $array['meta']['total']);
        $this->assertEquals(2, $array['meta']['per_page']);
        $this->assertEquals(3, $array['meta']['current_page']);
        $this->assertEquals(10, $array['meta']['last_page']);
        $this->assertTrue($array['meta']['has_more_pages']);
    }

    #[Test]
    public function test_single_item_total(): void
    {
        $paginator = new Paginator([['id' => 1]], total: 1, perPage: 10, currentPage: 1);

        $this->assertEquals(1, $paginator->lastPage());
        $this->assertFalse($paginator->hasMorePages());
    }
}

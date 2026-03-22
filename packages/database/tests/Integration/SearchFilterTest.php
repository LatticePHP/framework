<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Integration;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Lattice\Database\Filter\Filterable;
use Lattice\Database\Filter\QueryFilter;
use Lattice\Database\Search\Searchable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Search and Filter subsystems.
 *
 * Uses an in-memory SQLite database with real Eloquent models.
 */
final class SearchFilterTest extends TestCase
{
    private static bool $booted = false;

    protected function setUp(): void
    {
        if (!class_exists(Capsule::class)) {
            $this->markTestSkipped('illuminate/database is not installed.');
        }

        if (!self::$booted) {
            $this->bootDatabase();
            self::$booted = true;
        }

        // Clean tables before each test
        SearchFilterContact::query()->delete();
        SearchFilterCompany::query()->delete();

        $this->seedData();
    }

    private function bootDatabase(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = Capsule::schema();

        $schema->create('companies', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('contacts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('status')->default('lead');
            $table->integer('value')->default(0);
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        $company = SearchFilterCompany::create(['name' => 'Acme Corp']);

        SearchFilterContact::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'lead',
            'value' => 500,
            'company_id' => $company->id,
        ]);

        SearchFilterContact::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'status' => 'prospect',
            'value' => 1500,
            'company_id' => $company->id,
        ]);

        SearchFilterContact::create([
            'first_name' => 'Johnny',
            'last_name' => 'Appleseed',
            'email' => 'johnny@test.com',
            'status' => 'lead',
            'value' => 2000,
            'company_id' => null,
        ]);

        SearchFilterContact::create([
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice@example.com',
            'status' => 'customer',
            'value' => 3000,
            'company_id' => $company->id,
        ]);
    }

    // -------------------------------------------------------
    // Search tests (Scout-like)
    // -------------------------------------------------------

    #[Test]
    public function test_search_returns_matching_results(): void
    {
        $results = SearchFilterContact::search('john')->get();

        $this->assertCount(2, $results); // John Doe + Johnny Appleseed
        $names = array_map(fn ($r) => $r->first_name, $results);
        $this->assertContains('John', $names);
        $this->assertContains('Johnny', $names);
    }

    #[Test]
    public function test_search_with_where_constraint(): void
    {
        $results = SearchFilterContact::search('john')
            ->where('status', 'lead')
            ->get();

        // Both John and Johnny are leads
        $this->assertCount(2, $results);
    }

    #[Test]
    public function test_search_with_where_narrows_results(): void
    {
        // Search for 'john' but only prospects — neither John nor Johnny is a prospect
        $results = SearchFilterContact::search('john')
            ->where('status', 'prospect')
            ->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function test_search_with_order_and_limit(): void
    {
        $results = SearchFilterContact::search('john')
            ->orderBy('first_name', 'asc')
            ->limit(1)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('John', $results[0]->first_name);
    }

    // -------------------------------------------------------
    // QueryFilter tests (Spatie-style)
    // -------------------------------------------------------

    #[Test]
    public function test_filter_exact_match(): void
    {
        $filter = QueryFilter::fromRequest(['filter' => ['status' => 'lead']]);
        $results = SearchFilterContact::filter($filter)->get();

        $this->assertCount(2, $results);
        foreach ($results as $contact) {
            $this->assertSame('lead', $contact->status);
        }
    }

    #[Test]
    public function test_filter_multiple_values(): void
    {
        $filter = QueryFilter::fromRequest(['filter' => ['status' => 'lead,prospect']]);
        $results = SearchFilterContact::filter($filter)->get();

        $this->assertCount(3, $results);
        foreach ($results as $contact) {
            $this->assertContains($contact->status, ['lead', 'prospect']);
        }
    }

    #[Test]
    public function test_filter_range_gt(): void
    {
        $filter = QueryFilter::fromRequest(['filter' => ['value' => ['gt' => 1000]]]);
        $results = SearchFilterContact::filter($filter)->get();

        // Jane(1500), Johnny(2000), Alice(3000) — all > 1000
        $this->assertCount(3, $results);
        foreach ($results as $contact) {
            $this->assertGreaterThan(1000, $contact->value);
        }
    }

    #[Test]
    public function test_filter_null_value(): void
    {
        $filter = QueryFilter::fromRequest(['filter' => ['company_id' => 'null']]);
        $results = SearchFilterContact::filter($filter)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Johnny', $results[0]->first_name);
    }

    #[Test]
    public function test_sort_ascending(): void
    {
        $filter = QueryFilter::fromRequest(['sort' => 'first_name']);
        $results = SearchFilterContact::filter($filter)->get();

        $this->assertSame('Alice', $results[0]->first_name);
        $this->assertSame('Jane', $results[1]->first_name);
    }

    #[Test]
    public function test_sort_descending(): void
    {
        $filter = QueryFilter::fromRequest(['sort' => '-first_name']);
        $results = SearchFilterContact::filter($filter)->get();

        $this->assertSame('Johnny', $results[0]->first_name);
        $this->assertSame('John', $results[1]->first_name);
    }

    #[Test]
    public function test_sort_multiple(): void
    {
        $filter = QueryFilter::fromRequest(['sort' => 'status,first_name']);
        $results = SearchFilterContact::filter($filter)->get();

        // customer < lead < prospect (alphabetical)
        $this->assertSame('customer', $results[0]->status);
        // Leads sorted by first_name: John, Johnny
        $this->assertSame('John', $results[1]->first_name);
        $this->assertSame('Johnny', $results[2]->first_name);
        $this->assertSame('prospect', $results[3]->status);
    }

    #[Test]
    public function test_search_across_searchable_columns(): void
    {
        $filter = QueryFilter::fromRequest(['search' => 'john']);
        $results = SearchFilterContact::filter($filter)->get();

        // Matches: John (first_name), Johnny (first_name)
        $this->assertCount(2, $results);
    }

    #[Test]
    public function test_search_by_email(): void
    {
        $filter = QueryFilter::fromRequest(['search' => 'alice@example']);
        $results = SearchFilterContact::filter($filter)->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]->first_name);
    }

    #[Test]
    public function test_include_eager_loading(): void
    {
        $filter = QueryFilter::fromRequest(['include' => 'company']);
        $results = SearchFilterContact::filter($filter)->get();

        // All 4 contacts returned
        $this->assertCount(4, $results);

        // The ones with a company_id should have the relation loaded
        $withCompany = $results->filter(fn ($c) => $c->company_id !== null);
        foreach ($withCompany as $contact) {
            $this->assertTrue($contact->relationLoaded('company'));
            $this->assertSame('Acme Corp', $contact->company->name);
        }
    }

    #[Test]
    public function test_combined_search_filter_sort(): void
    {
        // Search for 'john', filter to leads, sort by value descending
        $filter = QueryFilter::fromRequest([
            'search' => 'john',
            'filter' => ['status' => 'lead'],
            'sort' => '-value',
        ]);
        $results = SearchFilterContact::filter($filter)->get();

        // Both John (500) and Johnny (2000) are leads matching 'john'
        $this->assertCount(2, $results);
        $this->assertSame('Johnny', $results[0]->first_name); // 2000 first
        $this->assertSame('John', $results[1]->first_name);   // 500 second
    }

    #[Test]
    public function test_invalid_filter_ignored(): void
    {
        // 'nonexistent' is not in allowedFilters
        $filter = QueryFilter::fromRequest(['filter' => ['nonexistent' => 'val']]);
        $results = SearchFilterContact::filter($filter)->get();

        // All records returned (filter ignored)
        $this->assertCount(4, $results);
    }

    #[Test]
    public function test_pagination_metadata(): void
    {
        $filter = QueryFilter::fromRequest(['per_page' => '2', 'page' => '1']);

        $this->assertSame(2, $filter->getPerPage());
        $this->assertSame(1, $filter->getPage());
    }

    #[Test]
    public function test_filter_range_from_to(): void
    {
        $filter = QueryFilter::fromRequest([
            'filter' => ['value' => ['from' => 500, 'to' => 1500]],
        ]);
        $results = SearchFilterContact::filter($filter)->get();

        // John (500) and Jane (1500) are within range
        $this->assertCount(2, $results);
        foreach ($results as $contact) {
            $this->assertGreaterThanOrEqual(500, $contact->value);
            $this->assertLessThanOrEqual(1500, $contact->value);
        }
    }
}

// -------------------------------------------------------
// Test models (defined inline for test isolation)
// -------------------------------------------------------

/**
 * @property int $id
 * @property string $name
 */
class SearchFilterCompany extends EloquentModel
{
    protected $table = 'companies';
    protected $guarded = [];
}

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $status
 * @property int $value
 * @property int|null $company_id
 */
class SearchFilterContact extends EloquentModel
{
    use Searchable;
    use Filterable;

    protected $table = 'contacts';
    protected $guarded = [];

    /** @var array<int, string> */
    protected array $searchable = ['first_name', 'last_name', 'email'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['status', 'company_id', 'value'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'last_name', 'first_name', 'value', 'status'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(SearchFilterCompany::class, 'company_id');
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Integration;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Lattice\Database\Filter\Filterable;
use Lattice\Database\Filter\QueryFilter;
use Lattice\Database\Model;
use Lattice\Database\Search\Searchable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// ── Test Models ─────────────────────────────────────────────────────────

class AdvTestPost extends Model
{
    use SoftDeletes;
    use Searchable;
    use Filterable;

    protected $table = 'test_posts';
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['title', 'body'];

    /** @var array<int, string> */
    protected array $allowedFilters = ['status', 'author_id'];

    /** @var array<int, string> */
    protected array $allowedSorts = ['created_at', 'title', 'published_at'];

    public function comments(): MorphMany
    {
        return $this->morphMany(AdvTestComment::class, 'commentable');
    }
}

class AdvTestComment extends Model
{
    protected $table = 'test_comments';
    protected $guarded = [];

    public function commentable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}

// ── Integration Tests ───────────────────────────────────────────────────

final class EloquentAdvancedTest extends TestCase
{
    private static bool $booted = false;

    protected function setUp(): void
    {
        if (!class_exists(Capsule::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        if (!self::$booted) {
            $this->bootDatabase();
            self::$booted = true;
        }

        // Clear booted model state so traits re-register
        AdvTestPost::clearBootedModels();
        AdvTestComment::clearBootedModels();

        // Clean tables before each test
        // Use raw query to bypass SoftDeletes
        Capsule::table('test_posts')->delete();
        Capsule::table('test_comments')->delete();
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

        $schema->create('test_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        $schema->create('test_comments', function (Blueprint $table): void {
            $table->id();
            $table->text('body');
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->timestamps();
        });
    }

    // ── 1. SoftDeletes: delete sets deleted_at ──────────────────────

    #[Test]
    public function test_soft_delete_sets_deleted_at(): void
    {
        $post = AdvTestPost::create(['title' => 'Post 1', 'body' => 'Body 1', 'status' => 'active']);
        $post->delete();

        // Model still exists in DB
        $raw = Capsule::table('test_posts')->where('id', $post->id)->first();
        $this->assertNotNull($raw, 'Record should still exist in database');
        $this->assertNotNull($raw->deleted_at, 'deleted_at should be set');
    }

    // ── 2. SoftDeletes: query excludes deleted ──────────────────────

    #[Test]
    public function test_soft_delete_query_excludes_deleted(): void
    {
        AdvTestPost::create(['title' => 'Keep 1', 'body' => 'Body']);
        AdvTestPost::create(['title' => 'Keep 2', 'body' => 'Body']);
        $toDelete = AdvTestPost::create(['title' => 'Delete Me', 'body' => 'Body']);

        $toDelete->delete();

        $count = AdvTestPost::count();
        $this->assertSame(2, $count, 'Normal query should exclude soft-deleted records');
    }

    // ── 3. SoftDeletes: withTrashed includes all ────────────────────

    #[Test]
    public function test_soft_delete_with_trashed_includes_all(): void
    {
        AdvTestPost::create(['title' => 'Post A', 'body' => 'Body']);
        AdvTestPost::create(['title' => 'Post B', 'body' => 'Body']);
        $toDelete = AdvTestPost::create(['title' => 'Post C', 'body' => 'Body']);

        $toDelete->delete();

        $countWithTrashed = AdvTestPost::withTrashed()->count();
        $this->assertSame(3, $countWithTrashed, 'withTrashed should include all records');
    }

    // ── 4. SoftDeletes: restore ─────────────────────────────────────

    #[Test]
    public function test_soft_delete_restore(): void
    {
        AdvTestPost::create(['title' => 'Post 1', 'body' => 'Body']);
        AdvTestPost::create(['title' => 'Post 2', 'body' => 'Body']);
        $toDelete = AdvTestPost::create(['title' => 'Post 3', 'body' => 'Body']);

        $toDelete->delete();
        $this->assertSame(2, AdvTestPost::count());

        $toDelete->restore();

        $this->assertNull($toDelete->fresh()->deleted_at, 'deleted_at should be null after restore');
        $this->assertSame(3, AdvTestPost::count(), 'Restored record should appear in normal queries');
    }

    // ── 5. SoftDeletes: forceDelete ─────────────────────────────────

    #[Test]
    public function test_soft_delete_force_delete(): void
    {
        $post = AdvTestPost::create(['title' => 'Force Delete Me', 'body' => 'Body']);
        $id = $post->id;

        $post->forceDelete();

        $raw = Capsule::table('test_posts')->where('id', $id)->first();
        $this->assertNull($raw, 'forceDelete should permanently remove the record from the DB');
    }

    // ── 6. Casts: array column ──────────────────────────────────────

    #[Test]
    public function test_casts_array_column(): void
    {
        $post = AdvTestPost::create([
            'title' => 'Tagged Post',
            'body' => 'Body',
            'tags' => ['tag1', 'tag2'],
        ]);

        // Re-fetch from DB to ensure casting on read
        $fetched = AdvTestPost::find($post->id);

        $this->assertIsArray($fetched->tags);
        $this->assertSame(['tag1', 'tag2'], $fetched->tags);
    }

    // ── 7. Casts: datetime column ───────────────────────────────────

    #[Test]
    public function test_casts_datetime_column(): void
    {
        $post = AdvTestPost::create([
            'title' => 'Published Post',
            'body' => 'Body',
            'published_at' => '2025-06-15 10:30:00',
        ]);

        $fetched = AdvTestPost::find($post->id);

        $this->assertInstanceOf(\DateTimeInterface::class, $fetched->published_at);
        $this->assertSame('2025', $fetched->published_at->format('Y'));
        $this->assertSame('06', $fetched->published_at->format('m'));
        $this->assertSame('15', $fetched->published_at->format('d'));
    }

    // ── 8. morphMany relationship ───────────────────────────────────

    #[Test]
    public function test_morph_many_relationship(): void
    {
        $post = AdvTestPost::create(['title' => 'Post with Comments', 'body' => 'Body']);

        AdvTestComment::create([
            'body' => 'First comment',
            'commentable_type' => AdvTestPost::class,
            'commentable_id' => $post->id,
        ]);
        AdvTestComment::create([
            'body' => 'Second comment',
            'commentable_type' => AdvTestPost::class,
            'commentable_id' => $post->id,
        ]);

        $comments = $post->comments;

        $this->assertCount(2, $comments);
        $this->assertSame('First comment', $comments[0]->body);
        $this->assertSame('Second comment', $comments[1]->body);
    }

    // ── 9. Searchable: search() ─────────────────────────────────────

    #[Test]
    public function test_searchable_search_returns_matching(): void
    {
        AdvTestPost::create(['title' => 'Hello World', 'body' => 'Welcome to LatticePHP']);
        AdvTestPost::create(['title' => 'Goodbye World', 'body' => 'Farewell']);
        AdvTestPost::create(['title' => 'Hello Again', 'body' => 'Another hello post']);

        $results = AdvTestPost::search('hello')->get();

        $this->assertCount(2, $results, 'Search should match posts containing "hello" in title or body');
        $titles = array_map(fn ($r) => $r->title, $results);
        $this->assertContains('Hello World', $titles);
        $this->assertContains('Hello Again', $titles);
    }

    #[Test]
    public function test_searchable_search_by_body(): void
    {
        AdvTestPost::create(['title' => 'Unrelated Title', 'body' => 'This mentions lattice inside']);
        AdvTestPost::create(['title' => 'Another Post', 'body' => 'No match here']);

        $results = AdvTestPost::search('lattice')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Unrelated Title', $results[0]->title);
    }

    // ── 10. Filterable: filter() ────────────────────────────────────

    #[Test]
    public function test_filterable_filter_exact_match(): void
    {
        AdvTestPost::create(['title' => 'Active Post 1', 'body' => 'Body', 'status' => 'active']);
        AdvTestPost::create(['title' => 'Active Post 2', 'body' => 'Body', 'status' => 'active']);
        AdvTestPost::create(['title' => 'Draft Post', 'body' => 'Body', 'status' => 'draft']);

        $filter = QueryFilter::fromRequest(['filter' => ['status' => 'active']]);
        $results = AdvTestPost::filter($filter)->get();

        $this->assertCount(2, $results);
        foreach ($results as $post) {
            $this->assertSame('active', $post->status);
        }
    }

    // ── 11. Filterable: sort ────────────────────────────────────────

    #[Test]
    public function test_filterable_sort_descending(): void
    {
        AdvTestPost::create(['title' => 'Alpha', 'body' => 'Body', 'status' => 'active']);
        AdvTestPost::create(['title' => 'Charlie', 'body' => 'Body', 'status' => 'active']);
        AdvTestPost::create(['title' => 'Bravo', 'body' => 'Body', 'status' => 'active']);

        $filter = QueryFilter::fromRequest(['sort' => '-title']);
        $results = AdvTestPost::filter($filter)->get();

        $this->assertSame('Charlie', $results[0]->title);
        $this->assertSame('Bravo', $results[1]->title);
        $this->assertSame('Alpha', $results[2]->title);
    }

    #[Test]
    public function test_filterable_sort_ascending(): void
    {
        AdvTestPost::create(['title' => 'Zulu', 'body' => 'Body']);
        AdvTestPost::create(['title' => 'Alpha', 'body' => 'Body']);
        AdvTestPost::create(['title' => 'Mike', 'body' => 'Body']);

        $filter = QueryFilter::fromRequest(['sort' => 'title']);
        $results = AdvTestPost::filter($filter)->get();

        $this->assertSame('Alpha', $results[0]->title);
        $this->assertSame('Mike', $results[1]->title);
        $this->assertSame('Zulu', $results[2]->title);
    }

    // ── 12. Filterable: multiple values ─────────────────────────────

    #[Test]
    public function test_filterable_multiple_values(): void
    {
        AdvTestPost::create(['title' => 'Active', 'body' => 'Body', 'status' => 'active']);
        AdvTestPost::create(['title' => 'Draft', 'body' => 'Body', 'status' => 'draft']);
        AdvTestPost::create(['title' => 'Archived', 'body' => 'Body', 'status' => 'archived']);

        $filter = QueryFilter::fromRequest(['filter' => ['status' => 'active,draft']]);
        $results = AdvTestPost::filter($filter)->get();

        $this->assertCount(2, $results);
        $statuses = $results->pluck('status')->all();
        $this->assertContains('active', $statuses);
        $this->assertContains('draft', $statuses);
    }
}

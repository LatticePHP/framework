<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Integration;

// Load the Resource class directly since it lives in the http package
require_once dirname(__DIR__, 3) . '/http/src/Resource.php';

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Model;
use Lattice\Database\Traits\Auditable;
use Lattice\Database\Traits\BelongsToTenant;
use Lattice\Database\Traits\BelongsToWorkspace;
use Lattice\Http\Resource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// ── Test Models ──────────────────────────────────────────────────────────

class Contact extends Model
{
    protected $table = 'contacts';

    protected $fillable = ['name', 'email', 'phone'];

    protected $casts = [
        'name' => 'string',
        'email' => 'string',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'contact_id');
    }
}

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = ['contact_id', 'title', 'body'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}

class WorkspacedProject extends Model
{
    use BelongsToWorkspace;

    protected $table = 'projects';

    protected $fillable = ['name', 'workspace_id'];

    /** @var int|null Static override for testing */
    public static ?int $testWorkspaceId = null;
}

class TenantedInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'invoices';

    protected $fillable = ['amount', 'tenant_id'];

    /** @var int|null Static override for testing */
    public static ?int $testTenantId = null;
}

class AuditedUser extends Model
{
    use Auditable;

    protected $table = 'audited_users';

    protected $fillable = ['name', 'email', 'password'];

    /** @var array<string> Fields excluded from audit log */
    public array $auditExclude = ['password'];
}

// ── Test Resource ────────────────────────────────────────────────────────

class ContactResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'posts' => $this->whenLoaded('posts', fn ($posts) => PostResource::collection($posts)),
            'phone' => $this->when($this->resource->phone !== null, $this->resource->phone),
        ];
    }
}

class PostResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
        ];
    }
}

// ── Tests ────────────────────────────────────────────────────────────────

final class EloquentModelTest extends TestCase
{
    private IlluminateDatabaseManager $db;

    protected function setUp(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        $this->db = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Clear booted state so traits re-register event listeners with the new dispatcher
        Contact::clearBootedModels();
        WorkspacedProject::clearBootedModels();
        TenantedInvoice::clearBootedModels();
        AuditedUser::clearBootedModels();

        $this->createSchema();

        // Reset static state
        WorkspacedProject::$testWorkspaceId = null;
        TenantedInvoice::$testTenantId = null;
        AuditedUser::clearAuditLog();
    }

    private function createSchema(): void
    {
        $schema = $this->db->schema();

        $schema->create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        $schema->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        $schema->create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });

        $schema->create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        $schema->create('audited_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
    }

    // ── CRUD ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_create_model(): void
    {
        $contact = Contact::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertNotNull($contact->id);
        $this->assertSame('Alice', $contact->name);
        $this->assertSame('alice@example.com', $contact->email);
    }

    #[Test]
    public function test_find_model(): void
    {
        $created = Contact::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $found = Contact::find($created->id);

        $this->assertNotNull($found);
        $this->assertSame('Bob', $found->name);
    }

    #[Test]
    public function test_update_model(): void
    {
        $contact = Contact::create(['name' => 'Charlie', 'email' => 'charlie@example.com']);

        $contact->update(['name' => 'Charles']);

        $this->assertSame('Charles', $contact->fresh()->name);
    }

    #[Test]
    public function test_delete_model(): void
    {
        $contact = Contact::create(['name' => 'Dave', 'email' => 'dave@example.com']);
        $id = $contact->id;

        $contact->delete();

        $this->assertNull(Contact::find($id));
    }

    #[Test]
    public function test_where_query(): void
    {
        Contact::create(['name' => 'Eve', 'email' => 'eve@example.com']);
        Contact::create(['name' => 'Frank', 'email' => 'frank@example.com']);
        Contact::create(['name' => 'Eve Clone', 'email' => 'eve2@example.com']);

        $eves = Contact::where('name', 'like', 'Eve%')->get();

        $this->assertCount(2, $eves);
    }

    // ── Relationships ────────────────────────────────────────────────────

    #[Test]
    public function test_has_many_relationship(): void
    {
        $contact = Contact::create(['name' => 'Grace', 'email' => 'grace@example.com']);

        Post::create(['contact_id' => $contact->id, 'title' => 'First Post', 'body' => 'Hello']);
        Post::create(['contact_id' => $contact->id, 'title' => 'Second Post', 'body' => 'World']);

        $posts = $contact->posts;

        $this->assertCount(2, $posts);
        $this->assertSame('First Post', $posts[0]->title);
    }

    #[Test]
    public function test_belongs_to_relationship(): void
    {
        $contact = Contact::create(['name' => 'Hank', 'email' => 'hank@example.com']);
        $post = Post::create(['contact_id' => $contact->id, 'title' => 'My Post']);

        $this->assertSame('Hank', $post->contact->name);
    }

    #[Test]
    public function test_eager_loading(): void
    {
        $contact = Contact::create(['name' => 'Ivy', 'email' => 'ivy@example.com']);
        Post::create(['contact_id' => $contact->id, 'title' => 'Eager Post']);

        $loaded = Contact::with('posts')->find($contact->id);

        $this->assertTrue($loaded->relationLoaded('posts'));
        $this->assertCount(1, $loaded->posts);
    }

    // ── Resource Serialization ───────────────────────────────────────────

    #[Test]
    public function test_resource_make(): void
    {
        $contact = Contact::create(['name' => 'Jack', 'email' => 'jack@example.com', 'phone' => '555-1234']);

        $resource = ContactResource::make($contact);
        $array = $resource->toArray();

        $this->assertSame($contact->id, $array['id']);
        $this->assertSame('Jack', $array['name']);
        $this->assertSame('jack@example.com', $array['email']);
        $this->assertSame('555-1234', $array['phone']);
        $this->assertNull($array['posts']); // not loaded
    }

    #[Test]
    public function test_resource_collection(): void
    {
        Contact::create(['name' => 'K1', 'email' => 'k1@example.com']);
        Contact::create(['name' => 'K2', 'email' => 'k2@example.com']);

        $contacts = Contact::all();
        $collection = ContactResource::collection($contacts);

        $this->assertCount(2, $collection);
        $this->assertSame('K1', $collection[0]['name']);
        $this->assertSame('K2', $collection[1]['name']);
    }

    #[Test]
    public function test_resource_when_loaded(): void
    {
        $contact = Contact::create(['name' => 'Liam', 'email' => 'liam@example.com']);
        Post::create(['contact_id' => $contact->id, 'title' => 'Post A']);

        // Without eager loading — posts should be null
        $resource = ContactResource::make($contact);
        $this->assertNull($resource->toArray()['posts']);

        // With eager loading — posts should be serialized
        $loaded = Contact::with('posts')->find($contact->id);
        $resource = ContactResource::make($loaded);
        $array = $resource->toArray();

        $this->assertIsArray($array['posts']);
        $this->assertCount(1, $array['posts']);
        $this->assertSame('Post A', $array['posts'][0]['title']);
    }

    #[Test]
    public function test_resource_json_serializable(): void
    {
        $contact = Contact::create(['name' => 'Mia', 'email' => 'mia@example.com']);
        $resource = ContactResource::make($contact);

        $json = json_encode($resource);
        $decoded = json_decode($json, true);

        $this->assertSame('Mia', $decoded['name']);
        $this->assertSame('mia@example.com', $decoded['email']);
    }

    #[Test]
    public function test_resource_when_condition(): void
    {
        // phone is null => when() should return null
        $contact = Contact::create(['name' => 'Nora', 'email' => 'nora@example.com']);
        $resource = ContactResource::make($contact);
        $array = $resource->toArray();

        $this->assertNull($array['phone']);
    }

    // ── BelongsToWorkspace ───────────────────────────────────────────────

    #[Test]
    public function test_belongs_to_workspace_auto_sets_on_create(): void
    {
        WorkspacedProject::$testWorkspaceId = 42;

        $project = WorkspacedProject::create(['name' => 'Alpha']);

        $this->assertSame(42, $project->workspace_id);
    }

    #[Test]
    public function test_belongs_to_workspace_scopes_queries(): void
    {
        // Create projects in different workspaces (without scope)
        WorkspacedProject::$testWorkspaceId = null;
        WorkspacedProject::create(['name' => 'WS1-A', 'workspace_id' => 1]);
        WorkspacedProject::create(['name' => 'WS1-B', 'workspace_id' => 1]);
        WorkspacedProject::create(['name' => 'WS2-A', 'workspace_id' => 2]);

        // Now scope to workspace 1
        WorkspacedProject::$testWorkspaceId = 1;
        $projects = WorkspacedProject::all();

        $this->assertCount(2, $projects);
        $this->assertSame('WS1-A', $projects[0]->name);
    }

    #[Test]
    public function test_belongs_to_workspace_no_scope_when_null(): void
    {
        WorkspacedProject::$testWorkspaceId = null;

        WorkspacedProject::create(['name' => 'No Scope 1', 'workspace_id' => 1]);
        WorkspacedProject::create(['name' => 'No Scope 2', 'workspace_id' => 2]);

        $all = WorkspacedProject::all();

        $this->assertCount(2, $all);
    }

    // ── BelongsToTenant ──────────────────────────────────────────────────

    #[Test]
    public function test_belongs_to_tenant_auto_sets_on_create(): void
    {
        TenantedInvoice::$testTenantId = 99;

        $invoice = TenantedInvoice::create(['amount' => 100.50]);

        $this->assertSame(99, $invoice->tenant_id);
    }

    #[Test]
    public function test_belongs_to_tenant_scopes_queries(): void
    {
        TenantedInvoice::$testTenantId = null;
        TenantedInvoice::create(['amount' => 50.00, 'tenant_id' => 10]);
        TenantedInvoice::create(['amount' => 75.00, 'tenant_id' => 10]);
        TenantedInvoice::create(['amount' => 200.00, 'tenant_id' => 20]);

        TenantedInvoice::$testTenantId = 10;
        $invoices = TenantedInvoice::all();

        $this->assertCount(2, $invoices);
    }

    // ── Auditable ────────────────────────────────────────────────────────

    #[Test]
    public function test_auditable_logs_create(): void
    {
        AuditedUser::create(['name' => 'Otto', 'email' => 'otto@example.com', 'password' => 'secret']);

        $log = AuditedUser::getAuditLog();

        $this->assertCount(1, $log);
        $this->assertSame('created', $log[0]['action']);
        $this->assertSame('Otto', $log[0]['new_values']['name']);
        // Password should be excluded
        $this->assertArrayNotHasKey('password', $log[0]['new_values']);
    }

    #[Test]
    public function test_auditable_logs_update(): void
    {
        $user = AuditedUser::create(['name' => 'Pat', 'email' => 'pat@example.com', 'password' => 'secret']);
        AuditedUser::clearAuditLog();

        $user->update(['name' => 'Patrick']);

        $log = AuditedUser::getAuditLog();

        $this->assertCount(1, $log);
        $this->assertSame('updated', $log[0]['action']);
        $this->assertSame('Pat', $log[0]['old_values']['name']);
        $this->assertSame('Patrick', $log[0]['new_values']['name']);
    }

    #[Test]
    public function test_auditable_logs_delete(): void
    {
        $user = AuditedUser::create(['name' => 'Quinn', 'email' => 'quinn@example.com', 'password' => 'secret']);
        AuditedUser::clearAuditLog();

        $user->delete();

        $log = AuditedUser::getAuditLog();

        $this->assertCount(1, $log);
        $this->assertSame('deleted', $log[0]['action']);
        // Old values should have name but not password
        $this->assertSame('Quinn', $log[0]['old_values']['name']);
        $this->assertArrayNotHasKey('password', $log[0]['old_values']);
    }

    #[Test]
    public function test_auditable_excludes_sensitive_fields(): void
    {
        $user = AuditedUser::create(['name' => 'Rita', 'email' => 'rita@example.com', 'password' => 'supersecret']);

        $log = AuditedUser::getAuditLog();

        $this->assertArrayNotHasKey('password', $log[0]['new_values']);
        $this->assertArrayHasKey('name', $log[0]['new_values']);
        $this->assertArrayHasKey('email', $log[0]['new_values']);
    }
}

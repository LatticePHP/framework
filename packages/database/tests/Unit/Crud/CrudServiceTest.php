<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit\Crud;

use Illuminate\Database\Eloquent\Model;
use Lattice\Auth\Principal;
use Lattice\Database\Crud\CrudService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// ── Test DTO ────────────────────────────────────────────────────────────

final class CreateDto
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}

final class UpdateDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}
}

// ── Fake Model ──────────────────────────────────────────────────────────

final class FakeModel extends Model
{
    protected $guarded = [];

    /** @var array<string, mixed>|null Track the last create() call */
    public static ?array $lastCreatedData = null;

    /** @var array<string, mixed>|null Track the last update() call */
    public ?array $lastUpdatedData = null;

    /** @var bool Track whether delete() was called */
    public bool $wasDeleted = false;

    /** @var self|null Instance returned by findOrFail */
    public static ?self $findResult = null;

    public static function create(array $attributes = []): self
    {
        self::$lastCreatedData = $attributes;

        $instance = new self();
        $instance->forceFill($attributes);
        $instance->setAttribute('id', 1);

        return $instance;
    }

    public static function findOrFail(mixed $id, array $columns = ['*']): self
    {
        if (self::$findResult !== null) {
            return self::$findResult;
        }

        $instance = new self();
        $instance->setAttribute('id', $id);

        return $instance;
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        $this->lastUpdatedData = $attributes;

        return true;
    }

    public function delete(): ?bool
    {
        $this->wasDeleted = true;

        return true;
    }

    public function fresh($with = []): ?self
    {
        return $this;
    }

    public static function resetState(): void
    {
        self::$lastCreatedData = null;
        self::$findResult = null;
    }
}

// ── Concrete CrudService for testing ────────────────────────────────────

final class TestCrudService extends CrudService
{
    /** @var list<string> Track hook invocations */
    public array $hooksCalled = [];

    protected function model(): string
    {
        return FakeModel::class;
    }

    protected function beforeCreate(array &$data, Principal $user): void
    {
        $this->hooksCalled[] = 'beforeCreate';
    }

    protected function afterCreate(Model $model, Principal $user): void
    {
        $this->hooksCalled[] = 'afterCreate';
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->hooksCalled[] = 'beforeUpdate';
    }

    protected function afterUpdate(Model $model): void
    {
        $this->hooksCalled[] = 'afterUpdate';
    }

    protected function beforeDelete(Model $model): void
    {
        $this->hooksCalled[] = 'beforeDelete';
    }

    protected function afterDelete(int $id): void
    {
        $this->hooksCalled[] = 'afterDelete';
    }
}

final class CustomOwnerService extends CrudService
{
    protected function model(): string
    {
        return FakeModel::class;
    }

    protected function ownerField(): string
    {
        return 'author_id';
    }
}

// ── Tests ───────────────────────────────────────────────────────────────

final class CrudServiceTest extends TestCase
{
    private TestCrudService $service;

    protected function setUp(): void
    {
        FakeModel::resetState();
        $this->service = new TestCrudService();
    }

    // ── dtoToArray ──────────────────────────────────────────────────────

    #[Test]
    public function test_dto_to_array_extracts_public_properties(): void
    {
        $dto = new CreateDto(name: 'Alice', email: 'alice@example.com');

        $ref = new \ReflectionMethod($this->service, 'dtoToArray');
        $result = $ref->invoke($this->service, $dto);

        $this->assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $result);
    }

    #[Test]
    public function test_dto_to_array_filters_nulls_when_enabled(): void
    {
        $dto = new UpdateDto(name: 'Bob', email: null);

        $ref = new \ReflectionMethod($this->service, 'dtoToArray');
        $result = $ref->invoke($this->service, $dto, true);

        $this->assertSame(['name' => 'Bob'], $result);
    }

    #[Test]
    public function test_dto_to_array_keeps_nulls_when_not_filtering(): void
    {
        $dto = new UpdateDto(name: 'Charlie', email: null);

        $ref = new \ReflectionMethod($this->service, 'dtoToArray');
        $result = $ref->invoke($this->service, $dto);

        $this->assertSame(['name' => 'Charlie', 'email' => null], $result);
    }

    // ── create() ────────────────────────────────────────────────────────

    #[Test]
    public function test_create_calls_model_create_with_owner_id(): void
    {
        $dto = new CreateDto(name: 'Alice', email: 'alice@example.com');
        $user = new Principal(id: 42);

        $model = $this->service->create($dto, $user);

        $this->assertInstanceOf(FakeModel::class, $model);
        $this->assertSame(42, FakeModel::$lastCreatedData['owner_id']);
        $this->assertSame('Alice', FakeModel::$lastCreatedData['name']);
        $this->assertSame('alice@example.com', FakeModel::$lastCreatedData['email']);
    }

    #[Test]
    public function test_create_uses_custom_owner_field(): void
    {
        $service = new CustomOwnerService();
        $dto = new CreateDto(name: 'Bob', email: 'bob@example.com');
        $user = new Principal(id: 99);

        $service->create($dto, $user);

        $this->assertArrayHasKey('author_id', FakeModel::$lastCreatedData);
        $this->assertSame(99, FakeModel::$lastCreatedData['author_id']);
        $this->assertArrayNotHasKey('owner_id', FakeModel::$lastCreatedData);
    }

    #[Test]
    public function test_create_calls_before_and_after_hooks(): void
    {
        $dto = new CreateDto(name: 'Alice', email: 'alice@example.com');
        $user = new Principal(id: 1);

        $this->service->create($dto, $user);

        $this->assertSame(['beforeCreate', 'afterCreate'], $this->service->hooksCalled);
    }

    // ── update() ────────────────────────────────────────────────────────

    #[Test]
    public function test_update_filters_nulls_and_calls_model_update(): void
    {
        $existing = new FakeModel();
        $existing->setAttribute('id', 5);
        FakeModel::$findResult = $existing;

        $dto = new UpdateDto(name: 'Updated', email: null);

        $this->service->update(5, $dto);

        $this->assertSame(['name' => 'Updated'], $existing->lastUpdatedData);
    }

    #[Test]
    public function test_update_calls_before_and_after_hooks(): void
    {
        $existing = new FakeModel();
        $existing->setAttribute('id', 5);
        FakeModel::$findResult = $existing;

        $dto = new UpdateDto(name: 'Updated');

        $this->service->update(5, $dto);

        $this->assertSame(['beforeUpdate', 'afterUpdate'], $this->service->hooksCalled);
    }

    // ── delete() ────────────────────────────────────────────────────────

    #[Test]
    public function test_delete_calls_model_delete(): void
    {
        $existing = new FakeModel();
        $existing->setAttribute('id', 7);
        FakeModel::$findResult = $existing;

        $this->service->delete(7);

        $this->assertTrue($existing->wasDeleted);
    }

    #[Test]
    public function test_delete_calls_before_and_after_hooks(): void
    {
        $existing = new FakeModel();
        $existing->setAttribute('id', 7);
        FakeModel::$findResult = $existing;

        $this->service->delete(7);

        $this->assertSame(['beforeDelete', 'afterDelete'], $this->service->hooksCalled);
    }

    // ── find() ──────────────────────────────────────────────────────────

    #[Test]
    public function test_find_returns_model(): void
    {
        $existing = new FakeModel();
        $existing->setAttribute('id', 3);
        FakeModel::$findResult = $existing;

        $result = $this->service->find(3);

        $this->assertSame($existing, $result);
    }
}

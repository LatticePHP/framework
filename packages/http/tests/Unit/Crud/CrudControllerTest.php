<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit\Crud;

use Illuminate\Database\Eloquent\Model;
use Lattice\Auth\Principal;
use Lattice\Database\Crud\CrudService;
use Lattice\Http\Crud\CrudController;
use Lattice\Http\Resource;
use Lattice\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// ── Fake Resource ───────────────────────────────────────────────────────

final class FakeResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

// ── Fake Model (standalone, not hitting a DB) ───────────────────────────

final class StubModel extends Model
{
    protected $guarded = [];

    public static ?self $deleteTarget = null;

    public function delete(): ?bool
    {
        self::$deleteTarget = $this;

        return true;
    }

    public static function findOrFail(mixed $id, array $columns = ['*']): self
    {
        $m = new self();
        $m->setAttribute('id', $id);
        $m->setAttribute('name', 'Found');

        return $m;
    }
}

// ── Fake CrudService ────────────────────────────────────────────────────

final class FakeCrudService extends CrudService
{
    public bool $deleteCalled = false;
    public ?int $deletedId = null;

    protected function model(): string
    {
        return StubModel::class;
    }

    public function delete(int $id): void
    {
        $this->deleteCalled = true;
        $this->deletedId = $id;
    }

    public function create(object $dto, Principal $user): Model
    {
        $m = new StubModel();
        $m->setAttribute('id', 1);
        $m->setAttribute('name', 'Created');

        return $m;
    }

    public function update(int $id, object $dto): Model
    {
        $m = new StubModel();
        $m->setAttribute('id', $id);
        $m->setAttribute('name', 'Updated');

        return $m;
    }
}

// ── Concrete Controller for testing ─────────────────────────────────────

final class TestCrudController extends CrudController
{
    private FakeCrudService $svc;

    public function __construct()
    {
        $this->svc = new FakeCrudService();
    }

    protected function service(): CrudService
    {
        return $this->svc;
    }

    protected function resourceClass(): string
    {
        return FakeResource::class;
    }

    protected function modelClass(): string
    {
        return StubModel::class;
    }

    public function getService(): FakeCrudService
    {
        return $this->svc;
    }

    /**
     * Expose storeResponse for testing.
     */
    public function testStoreResponse(object $model): Response
    {
        return $this->storeResponse($model);
    }

    /**
     * Expose updateResponse for testing.
     */
    public function testUpdateResponse(object $model): Response
    {
        return $this->updateResponse($model);
    }
}

// ── Tests ───────────────────────────────────────────────────────────────

final class CrudControllerTest extends TestCase
{
    private TestCrudController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestCrudController();
    }

    #[Test]
    public function test_destroy_delegates_to_service_and_returns_204(): void
    {
        $response = $this->controller->destroy(42);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull($response->getBody());
        $this->assertTrue($this->controller->getService()->deleteCalled);
        $this->assertSame(42, $this->controller->getService()->deletedId);
    }

    #[Test]
    public function test_store_response_returns_201_with_resource_data(): void
    {
        $model = new StubModel();
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Alice');

        $response = $this->controller->testStoreResponse($model);

        $this->assertSame(201, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $body['data']);
    }

    #[Test]
    public function test_update_response_returns_200_with_resource_data(): void
    {
        $model = new StubModel();
        $model->setAttribute('id', 5);
        $model->setAttribute('name', 'Bob');

        $response = $this->controller->testUpdateResponse($model);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertSame(['id' => 5, 'name' => 'Bob'], $body['data']);
    }

    #[Test]
    public function test_index_relations_defaults_to_empty(): void
    {
        $ref = new \ReflectionMethod($this->controller, 'indexRelations');
        $result = $ref->invoke($this->controller);

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_show_relations_defaults_to_empty(): void
    {
        $ref = new \ReflectionMethod($this->controller, 'showRelations');
        $result = $ref->invoke($this->controller);

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_service_returns_crud_service(): void
    {
        $ref = new \ReflectionMethod($this->controller, 'service');
        $result = $ref->invoke($this->controller);

        $this->assertInstanceOf(CrudService::class, $result);
    }

    #[Test]
    public function test_resource_class_returns_correct_class(): void
    {
        $ref = new \ReflectionMethod($this->controller, 'resourceClass');
        $result = $ref->invoke($this->controller);

        $this->assertSame(FakeResource::class, $result);
    }

    #[Test]
    public function test_model_class_returns_correct_class(): void
    {
        $ref = new \ReflectionMethod($this->controller, 'modelClass');
        $result = $ref->invoke($this->controller);

        $this->assertSame(StubModel::class, $result);
    }
}

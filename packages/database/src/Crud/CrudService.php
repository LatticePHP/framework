<?php

declare(strict_types=1);

namespace Lattice\Database\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Lattice\Auth\Principal;

/**
 * Abstract base class for mechanical CRUD operations.
 *
 * Services with no custom business logic inherit these methods unchanged.
 * Override hooks (beforeCreate, afterCreate, etc.) for custom behaviour.
 *
 * Features:
 * - Transaction-wrapped create/update/delete (atomic with hooks)
 * - Auto owner_id / workspace_id assignment
 * - Null-filtering for partial updates
 * - Database constraint errors → ValidationException
 * - Lifecycle hooks (before/after create/update/delete)
 *
 * @template TModel of Model
 */
abstract class CrudService
{
    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /**
     * Column name for owner assignment (override for 'author_id' etc.).
     */
    protected function ownerField(): string
    {
        return 'owner_id';
    }

    /**
     * Relations to eager-load after create/update for the API response.
     *
     * @return list<string>
     */
    protected function responseRelations(): array
    {
        return [];
    }

    /**
     * @return TModel
     */
    public function find(int $id): Model
    {
        return ($this->model())::findOrFail($id);
    }

    /**
     * Create a new model from DTO, wrapped in a transaction.
     *
     * @return TModel
     */
    public function create(object $dto, Principal $user): Model
    {
        return $this->transaction(function () use ($dto, $user): Model {
            $data = $this->dtoToArray($dto);
            $data[$this->ownerField()] = (int) $user->getId();

            $this->beforeCreate($data, $user);
            $model = ($this->model())::create($data);
            $this->afterCreate($model, $user);

            if (!empty($this->responseRelations())) {
                $model->load($this->responseRelations());
            }

            return $model;
        });
    }

    /**
     * Update a model from DTO (partial — null fields ignored), wrapped in a transaction.
     *
     * @return TModel
     */
    public function update(int $id, object $dto): Model
    {
        return $this->transaction(function () use ($id, $dto): Model {
            $model = $this->find($id);
            $data = $this->dtoToArray($dto, filterNulls: true);

            $this->beforeUpdate($model, $data);
            $model->update($data);
            $this->afterUpdate($model);

            $fresh = $model->fresh();

            if ($fresh !== null && !empty($this->responseRelations())) {
                $fresh->load($this->responseRelations());
            }

            return $fresh ?? $model;
        });
    }

    /**
     * Soft-delete (or hard-delete) a model, wrapped in a transaction.
     */
    public function delete(int $id): void
    {
        $this->transaction(function () use ($id): void {
            $model = $this->find($id);
            $this->beforeDelete($model);
            $model->delete();
            $this->afterDelete($id);
        });
    }

    // --- Hooks (override in subclass for custom logic) ---

    /**
     * @param array<string, mixed> &$data
     */
    protected function beforeCreate(array &$data, Principal $user): void {}

    protected function afterCreate(Model $model, Principal $user): void {}

    /**
     * @param array<string, mixed> &$data
     */
    protected function beforeUpdate(Model $model, array &$data): void {}

    protected function afterUpdate(Model $model): void {}

    protected function beforeDelete(Model $model): void {}

    protected function afterDelete(int $id): void {}

    // --- DTO mapping ---

    /**
     * Extract public properties from a DTO into an associative array.
     *
     * @return array<string, mixed>
     */
    protected function dtoToArray(object $dto, bool $filterNulls = false): array
    {
        $data = [];
        $ref = new \ReflectionClass($dto);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isInitialized($dto)) {
                $data[$prop->getName()] = $prop->getValue($dto);
            }
        }

        if ($filterNulls) {
            $data = array_filter($data, static fn(mixed $v): bool => $v !== null);
        }

        return $data;
    }

    // --- Transaction wrapper ---

    /**
     * Execute a callback inside a database transaction.
     *
     * Catches database constraint violations and converts them
     * to validation-style exceptions (422) instead of 500 errors.
     *
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    private function transaction(callable $callback): mixed
    {
        $connection = ($this->model())::query()->getConnection();

        try {
            return $connection->transaction($callback);
        } catch (QueryException $e) {
            // Convert integrity constraint violations to validation errors
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed')
                || str_contains($e->getMessage(), 'Duplicate entry')
                || str_contains($e->getMessage(), 'unique constraint')
            ) {
                throw new \InvalidArgumentException(
                    'A record with the given values already exists.',
                    422,
                    $e,
                );
            }

            throw $e;
        }
    }
}

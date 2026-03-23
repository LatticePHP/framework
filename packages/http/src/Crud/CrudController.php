<?php

declare(strict_types=1);

namespace Lattice\Http\Crud;

use Lattice\Database\Crud\CrudService;
use Lattice\Database\Filter\QueryFilter;
use Lattice\Http\Request;
use Lattice\Http\Resource;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;

/**
 * Base controller providing generic index/show/destroy endpoints.
 *
 * Subclasses define store() and update() with concrete DTO types so the
 * parameter resolver can hydrate the correct body object.
 *
 * Convenience helpers storeResponse() and updateResponse() keep subclass
 * methods short.
 */
abstract class CrudController
{
    abstract protected function service(): CrudService;

    /** @return class-string<Resource> */
    abstract protected function resourceClass(): string;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    abstract protected function modelClass(): string;

    /**
     * Relations to eager-load on the index listing.
     *
     * @return list<string>
     */
    protected function indexRelations(): array
    {
        return [];
    }

    /**
     * Relations to eager-load on a single resource.
     *
     * @return list<string>
     */
    protected function showRelations(): array
    {
        return [];
    }

    #[Get('/')]
    public function index(Request $request): Response
    {
        $filter = QueryFilter::fromRequest($request->query);
        $model = $this->modelClass();
        $query = $model::filter($filter);

        if (!empty($this->indexRelations())) {
            $query->with($this->indexRelations());
        }

        $paginator = $query->paginate(
            $filter->getPerPage(),
            ['*'],
            'page',
            $filter->getPage(),
        );

        return ResponseFactory::paginated($paginator, $this->resourceClass());
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $model = $this->modelClass();
        $query = $model::query();

        if (!empty($this->showRelations())) {
            $query->with($this->showRelations());
        }

        $item = $query->findOrFail($id);
        $resourceClass = $this->resourceClass();

        return ResponseFactory::json([
            'data' => $resourceClass::make($item)->toArray(),
        ]);
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        $this->service()->delete($id);

        return ResponseFactory::noContent();
    }

    /**
     * Build a 201 Created response wrapping a model through the resource class.
     *
     * Use in subclass store() methods:
     *
     *     #[Post('/')]
     *     public function store(#[Body] CreateDto $dto, #[CurrentUser] Principal $user): Response
     *     {
     *         return $this->storeResponse($this->service()->create($dto, $user));
     *     }
     */
    protected function storeResponse(object $model): Response
    {
        // Eager-load show relations to avoid N+1 in the resource serializer
        if (method_exists($model, 'load') && !empty($this->showRelations())) {
            $model->load($this->showRelations());
        }

        $resourceClass = $this->resourceClass();

        return ResponseFactory::created([
            'data' => $resourceClass::make($model)->toArray(),
        ]);
    }

    /**
     * Build a 200 JSON response wrapping a model through the resource class.
     *
     * Use in subclass update() methods:
     *
     *     #[Put('/:id')]
     *     public function update(#[Param] int $id, #[Body] UpdateDto $dto): Response
     *     {
     *         return $this->updateResponse($this->service()->update($id, $dto));
     *     }
     */
    protected function updateResponse(object $model): Response
    {
        if (method_exists($model, 'load') && !empty($this->showRelations())) {
            $model->load($this->showRelations());
        }

        $resourceClass = $this->resourceClass();

        return ResponseFactory::json([
            'data' => $resourceClass::make($model)->toArray(),
        ]);
    }
}

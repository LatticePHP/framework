<?php

declare(strict_types=1);

namespace Lattice\Http;

use Illuminate\Database\Eloquent\Model;
use Lattice\Http\Exception\BadRequestException;
use Lattice\Http\Exception\NotFoundException;

final class ModelResolver
{
    /**
     * Resolve an Eloquent model instance from a route parameter value.
     *
     * @param class-string $modelClass The fully qualified model class name
     * @param mixed $id The route parameter value (typically the primary key)
     * @return object The resolved model instance
     *
     * @throws NotFoundException If the model cannot be found
     * @throws BadRequestException If the class is not an Eloquent model
     */
    public function resolve(string $modelClass, mixed $id): object
    {
        if (is_subclass_of($modelClass, Model::class)) {
            $model = $modelClass::find($id);

            if ($model === null) {
                throw new NotFoundException("Resource not found: {$modelClass} with ID {$id}");
            }

            return $model;
        }

        throw new BadRequestException("Cannot resolve {$modelClass} from route parameter");
    }
}

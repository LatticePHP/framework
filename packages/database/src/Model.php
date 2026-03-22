<?php

declare(strict_types=1);

namespace Lattice\Database;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Base model for LatticePHP applications.
 *
 * Extends Eloquent's Model directly. Use Illuminate's HasFactory trait
 * in your models for factory support.
 */
abstract class Model extends EloquentModel
{
    // Inherits everything from Eloquent.
    // No method overrides — keeps full compatibility with Illuminate traits.
}

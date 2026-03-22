<?php

declare(strict_types=1);

namespace Lattice\Database\Illuminate;

/**
 * Eloquent support within LatticePHP modules.
 *
 * IlluminateDatabaseManager calls bootEloquent() during construction,
 * so Eloquent models work out of the box once the database module is
 * registered.
 *
 * USAGE IN A MODULE PROVIDER:
 *
 *     use App\Models\User;
 *     use Lattice\Database\Illuminate\IlluminateDatabaseManager;
 *
 *     final class UserProvider
 *     {
 *         public function __construct(
 *             private readonly IlluminateDatabaseManager $db,
 *         ) {}
 *
 *         public function findById(int $id): ?User
 *         {
 *             // Eloquent models work directly:
 *             return User::find($id);
 *         }
 *
 *         public function search(string $query): Collection
 *         {
 *             return User::where('name', 'like', "%{$query}%")->get();
 *         }
 *     }
 *
 * DEFINING ELOQUENT MODELS:
 *
 *     use Illuminate\Database\Eloquent\Model;
 *
 *     class User extends Model
 *     {
 *         protected $fillable = ['name', 'email'];
 *     }
 *
 * RELATIONSHIPS, SCOPES, CASTS:
 *
 *     All standard Eloquent features are available — relationships,
 *     scopes, attribute casting, observers, events, etc. — because
 *     we're running the real illuminate/database under the hood.
 *
 * MULTIPLE CONNECTIONS:
 *
 *     // In your model:
 *     protected $connection = 'analytics';
 *
 *     // Register the connection:
 *     $db->addConnection([
 *         'driver' => 'pgsql',
 *         'host' => '...',
 *         'database' => 'analytics',
 *     ], 'analytics');
 */
final class EloquentSupport
{
    // This class is intentionally empty.
    // It exists as documentation for how to use Eloquent within LatticePHP.
    // See the class docblock above for usage examples.

    private function __construct()
    {
        // Not instantiable — documentation only.
    }
}

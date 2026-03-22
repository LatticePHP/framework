<?php

declare(strict_types=1);

/**
 * Seed the CRM database with test data.
 *
 * Usage: php bin/seed.php
 */

// Use monorepo root autoloader, fall back to local vendor
$autoloadPaths = [
    __DIR__ . '/../../../../vendor/autoload.php', // monorepo root
    __DIR__ . '/../vendor/autoload.php',          // local vendor
];
foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        break;
    }
}

use App\Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Lattice\Core\Environment\EnvLoader;

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $vars = EnvLoader::loadFile($envFile);
    foreach ($vars as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Setup database connection
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/../' . ($_ENV['DB_DATABASE'] ?? 'database/crm.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Seeding database...\n";

$seeder = new DatabaseSeeder();
$seeder->run();

echo "Done! Database seeded successfully.\n";
echo "  Login credentials:\n";
echo "    alice@example.com / password (admin)\n";
echo "    bob@example.com / password (manager)\n";
echo "    carol@example.com / password (user)\n";

<?php

declare(strict_types=1);

/**
 * Run database migrations for the CRM example.
 *
 * Usage: php bin/migrate.php [--fresh]
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

// Create database file if it doesn't exist
$dbPath = __DIR__ . '/../' . ($_ENV['DB_DATABASE'] ?? 'database/crm.sqlite');
if (!file_exists($dbPath)) {
    touch($dbPath);
    echo "Created database file: {$dbPath}\n";
}

// Check for --fresh flag
$fresh = in_array('--fresh', $argv ?? [], true);

// Discover and run migrations in order
$migrationsPath = __DIR__ . '/../database/migrations';
$files = glob("{$migrationsPath}/*.php");
sort($files);

echo "Running migrations" . ($fresh ? " (fresh)" : "") . "...\n";

foreach ($files as $file) {
    $migration = require $file;
    $name = basename($file, '.php');

    if ($fresh && method_exists($migration, 'down')) {
        echo "  Rolling back: {$name}\n";
        $migration->down();
    }

    echo "  Migrating: {$name}\n";
    $migration->up();
}

echo "Done! All migrations completed.\n";

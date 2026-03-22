<?php

declare(strict_types=1);

use Lattice\Core\Application;
use App\AppModule;

// Load environment
$basePath = dirname(__DIR__);
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    \Lattice\Core\Environment\EnvLoader::loadFile($envFile);
}

// Boot Eloquent with SQLite
$dbPath = $basePath . '/' . ($_ENV['DB_DATABASE'] ?? 'database/crm.sqlite');
if (class_exists(\Illuminate\Database\Capsule\Manager::class)) {
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $dbPath,
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
}

return Application::configure(basePath: $basePath)
    ->withModules([
        AppModule::class,
    ])
    ->withHttp()
    ->create();

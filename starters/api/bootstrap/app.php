<?php

declare(strict_types=1);

use Lattice\Core\Application;
use App\Modules\App\AppModule;

// Load environment
$basePath = dirname(__DIR__);
if (file_exists($basePath . '/.env')) {
    \Lattice\Core\Environment\EnvLoader::loadFile($basePath . '/.env');
}

// Boot Eloquent
if (class_exists(\Illuminate\Database\Capsule\Manager::class)) {
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $dbConfig = require $basePath . '/config/database.php';
    $default = $dbConfig['default'] ?? 'sqlite';
    $capsule->addConnection($dbConfig['connections'][$default] ?? [
        'driver' => 'sqlite',
        'database' => $basePath . '/database/database.sqlite',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
}

return Application::configure(basePath: $basePath)
    ->withModules([AppModule::class])
    ->withHttp()
    ->create();

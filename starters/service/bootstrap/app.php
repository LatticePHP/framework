<?php

declare(strict_types=1);

use Lattice\Core\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withModules([
        App\AppModule::class,
    ])
    ->withHttp()
    ->create();

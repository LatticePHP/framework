<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Lattice\Core\Application;
use Lattice\Core\Http\RequestFactory;
use Lattice\Core\Http\ResponseEmitter;

// Boot the application
$app = require __DIR__ . '/../bootstrap/app.php';

// Handle the request
$request = RequestFactory::fromGlobals();
$response = $app->handleRequest($request);

// Send the response
ResponseEmitter::emit($response);

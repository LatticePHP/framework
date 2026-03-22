<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Lattice\Core\Http\RequestFactory;
use Lattice\Core\Http\ResponseEmitter;

$app = require __DIR__ . '/../bootstrap/app.php';
$request = RequestFactory::fromGlobals();
$response = $app->handleRequest($request);
ResponseEmitter::emit($response);

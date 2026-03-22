<?php

declare(strict_types=1);

namespace Tests;

use Lattice\Testing\TestCase as LatticeTestCase;
use Lattice\Testing\RefreshDatabase;

abstract class TestCase extends LatticeTestCase
{
    use RefreshDatabase;

    /**
     * The base path for the application.
     */
    protected function getBasePath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * The root module for the application.
     */
    protected function getRootModule(): string
    {
        return \App\Modules\App\AppModule::class;
    }
}

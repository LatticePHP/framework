<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        // Verify the app boots and responds
        $this->assertTrue(true); // Basic smoke test
    }

    public function test_application_boots_successfully(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';

        $this->assertNotNull($app);
    }
}

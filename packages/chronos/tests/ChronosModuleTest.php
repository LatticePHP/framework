<?php

declare(strict_types=1);

namespace Lattice\Chronos\Tests;

use Lattice\Chronos\ChronosModule;
use Lattice\Chronos\ChronosServiceProvider;
use Lattice\Chronos\Http\ChronosAdminGuard;
use Lattice\Chronos\InMemoryChronosEventStore;
use Lattice\Http\Request;
use Lattice\Module\Attribute\Module;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ChronosModuleTest extends TestCase
{
    #[Test]
    public function it_has_module_attribute(): void
    {
        $ref = new ReflectionClass(ChronosModule::class);
        $attrs = $ref->getAttributes(Module::class);

        $this->assertCount(1, $attrs);

        $module = $attrs[0]->newInstance();
        $this->assertContains(
            \Lattice\Chronos\Http\ChronosController::class,
            $module->controllers,
        );
        $this->assertContains(
            ChronosAdminGuard::class,
            $module->providers,
        );
    }

    #[Test]
    public function it_defines_routes(): void
    {
        $routes = ChronosModule::routes();

        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);

        // Verify list endpoint
        $listRoute = $this->findRoute($routes, 'GET', '/api/chronos/workflows');
        $this->assertNotNull($listRoute);
        $this->assertSame('list', $listRoute['handler']);

        // Verify detail endpoint
        $detailRoute = $this->findRoute($routes, 'GET', '/api/chronos/workflows/{id}');
        $this->assertNotNull($detailRoute);
        $this->assertSame('detail', $detailRoute['handler']);

        // Verify events endpoint
        $eventsRoute = $this->findRoute($routes, 'GET', '/api/chronos/workflows/{id}/events');
        $this->assertNotNull($eventsRoute);
        $this->assertSame('events', $eventsRoute['handler']);

        // Verify signal endpoint
        $signalRoute = $this->findRoute($routes, 'POST', '/api/chronos/workflows/{id}/signal');
        $this->assertNotNull($signalRoute);
        $this->assertSame('signal', $signalRoute['handler']);

        // Verify retry endpoint
        $retryRoute = $this->findRoute($routes, 'POST', '/api/chronos/workflows/{id}/retry');
        $this->assertNotNull($retryRoute);

        // Verify cancel endpoint
        $cancelRoute = $this->findRoute($routes, 'POST', '/api/chronos/workflows/{id}/cancel');
        $this->assertNotNull($cancelRoute);

        // Verify stats endpoint
        $statsRoute = $this->findRoute($routes, 'GET', '/api/chronos/stats');
        $this->assertNotNull($statsRoute);
        $this->assertSame('stats', $statsRoute['handler']);
    }

    #[Test]
    public function guard_allows_all_when_no_callback(): void
    {
        $guard = new ChronosAdminGuard();

        $request = new Request('GET', '/api/chronos/workflows');

        $this->assertTrue($guard->check($request));
    }

    #[Test]
    public function guard_denies_when_callback_returns_false(): void
    {
        $guard = new ChronosAdminGuard(fn () => false);

        $request = new Request('GET', '/api/chronos/workflows');

        $this->assertFalse($guard->check($request));
    }

    #[Test]
    public function guard_allows_when_callback_returns_true(): void
    {
        $guard = new ChronosAdminGuard(fn () => true);

        $request = new Request('GET', '/api/chronos/workflows');

        $this->assertTrue($guard->check($request));
    }

    #[Test]
    public function guard_deny_returns_403_response(): void
    {
        $guard = new ChronosAdminGuard();

        $response = $guard->deny();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertIsArray($response->getBody());
        $this->assertSame(403, $response->getBody()['status']);
        $this->assertSame('Forbidden', $response->getBody()['title']);
    }

    #[Test]
    public function guard_receives_request_in_callback(): void
    {
        $receivedRequest = null;
        $guard = new ChronosAdminGuard(function (Request $request) use (&$receivedRequest) {
            $receivedRequest = $request;
            return $request->getHeader('X-Admin') === 'true';
        });

        $adminRequest = new Request(
            'GET',
            '/api/chronos/workflows',
            headers: ['X-Admin' => 'true'],
        );
        $this->assertTrue($guard->check($adminRequest));
        $this->assertSame($adminRequest, $receivedRequest);

        $normalRequest = new Request('GET', '/api/chronos/workflows');
        $this->assertFalse($guard->check($normalRequest));
    }

    #[Test]
    public function service_provider_creates_all_components(): void
    {
        $eventStore = new InMemoryChronosEventStore();
        $registry = new WorkflowRegistry();
        $executor = new SyncActivityExecutor();
        $runtime = new WorkflowRuntime($eventStore, $executor, $registry);

        $provider = new ChronosServiceProvider($eventStore, $runtime);

        $this->assertInstanceOf(ChronosAdminGuard::class, $provider->createGuard());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowListAction::class, $provider->createListAction());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowDetailAction::class, $provider->createDetailAction());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowEventsAction::class, $provider->createEventsAction());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowSignalAction::class, $provider->createSignalAction());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowRetryAction::class, $provider->createRetryAction());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowCancelAction::class, $provider->createCancelAction());
        $this->assertInstanceOf(\Lattice\Chronos\Api\WorkflowStatsAction::class, $provider->createStatsAction());
        $this->assertInstanceOf(\Lattice\Chronos\Sse\WorkflowSseController::class, $provider->createSseController());
        $this->assertInstanceOf(\Lattice\Chronos\Http\ChronosController::class, $provider->createController());
    }

    #[Test]
    public function controller_applies_guard_to_all_routes(): void
    {
        $eventStore = new InMemoryChronosEventStore();
        $registry = new WorkflowRegistry();
        $executor = new SyncActivityExecutor();
        $runtime = new WorkflowRuntime($eventStore, $executor, $registry);

        $provider = new ChronosServiceProvider($eventStore, $runtime, fn () => false);
        $controller = $provider->createController();

        $request = new Request('GET', '/api/chronos/workflows');

        // All routes should return 403 when guard denies
        $this->assertSame(403, $controller->list($request)->getStatusCode());
        $this->assertSame(403, $controller->detail($request)->getStatusCode());
        $this->assertSame(403, $controller->events($request)->getStatusCode());
        $this->assertSame(403, $controller->signal($request)->getStatusCode());
        $this->assertSame(403, $controller->retry($request)->getStatusCode());
        $this->assertSame(403, $controller->cancel($request)->getStatusCode());
        $this->assertSame(403, $controller->stats($request)->getStatusCode());
    }

    /**
     * @param list<array{method: string, path: string, handler: string}> $routes
     * @return array{method: string, path: string, handler: string}|null
     */
    private function findRoute(array $routes, string $method, string $path): ?array
    {
        foreach ($routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                return $route;
            }
        }

        return null;
    }
}

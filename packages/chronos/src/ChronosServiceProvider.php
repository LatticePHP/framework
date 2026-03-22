<?php

declare(strict_types=1);

namespace Lattice\Chronos;

use Lattice\Chronos\Api\WorkflowCancelAction;
use Lattice\Chronos\Api\WorkflowDetailAction;
use Lattice\Chronos\Api\WorkflowEventsAction;
use Lattice\Chronos\Api\WorkflowListAction;
use Lattice\Chronos\Api\WorkflowRetryAction;
use Lattice\Chronos\Api\WorkflowSignalAction;
use Lattice\Chronos\Api\WorkflowStatsAction;
use Lattice\Chronos\Http\ChronosAdminGuard;
use Lattice\Chronos\Http\ChronosController;
use Lattice\Chronos\Sse\WorkflowSseController;

/**
 * Service provider for standalone Chronos registration outside the module system.
 *
 * Provides factory methods to create all Chronos components with
 * the given event store and workflow runtime.
 */
final class ChronosServiceProvider
{
    /** @var (\Closure(Request): bool)|null */
    private readonly ?\Closure $authorizeCallback;

    /**
     * @param (callable(Request): bool)|null $authorizeCallback
     */
    public function __construct(
        private readonly ChronosEventStoreInterface $eventStore,
        private readonly \Lattice\Workflow\Runtime\WorkflowRuntime $runtime,
        ?callable $authorizeCallback = null,
    ) {
        $this->authorizeCallback = $authorizeCallback !== null ? $authorizeCallback(...) : null;
    }

    public function createGuard(): ChronosAdminGuard
    {
        return new ChronosAdminGuard($this->authorizeCallback);
    }

    public function createListAction(): WorkflowListAction
    {
        return new WorkflowListAction($this->eventStore);
    }

    public function createDetailAction(): WorkflowDetailAction
    {
        return new WorkflowDetailAction($this->eventStore);
    }

    public function createEventsAction(): WorkflowEventsAction
    {
        return new WorkflowEventsAction($this->eventStore);
    }

    public function createSignalAction(): WorkflowSignalAction
    {
        return new WorkflowSignalAction($this->eventStore, $this->runtime);
    }

    public function createRetryAction(): WorkflowRetryAction
    {
        return new WorkflowRetryAction($this->eventStore, $this->runtime);
    }

    public function createCancelAction(): WorkflowCancelAction
    {
        return new WorkflowCancelAction($this->eventStore, $this->runtime);
    }

    public function createStatsAction(): WorkflowStatsAction
    {
        return new WorkflowStatsAction($this->eventStore);
    }

    public function createSseController(): WorkflowSseController
    {
        return new WorkflowSseController($this->eventStore);
    }

    public function createController(): ChronosController
    {
        return new ChronosController(
            $this->createGuard(),
            $this->createListAction(),
            $this->createDetailAction(),
            $this->createEventsAction(),
            $this->createSignalAction(),
            $this->createRetryAction(),
            $this->createCancelAction(),
            $this->createStatsAction(),
        );
    }
}

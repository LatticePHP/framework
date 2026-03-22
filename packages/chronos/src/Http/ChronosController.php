<?php

declare(strict_types=1);

namespace Lattice\Chronos\Http;

use Lattice\Chronos\Api\WorkflowCancelAction;
use Lattice\Chronos\Api\WorkflowDetailAction;
use Lattice\Chronos\Api\WorkflowEventsAction;
use Lattice\Chronos\Api\WorkflowListAction;
use Lattice\Chronos\Api\WorkflowRetryAction;
use Lattice\Chronos\Api\WorkflowSignalAction;
use Lattice\Chronos\Api\WorkflowStatsAction;
use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * Main HTTP controller for the Chronos API.
 *
 * Delegates to individual action classes and applies the admin guard.
 */
final class ChronosController
{
    public function __construct(
        private readonly ChronosAdminGuard $guard,
        private readonly WorkflowListAction $listAction,
        private readonly WorkflowDetailAction $detailAction,
        private readonly WorkflowEventsAction $eventsAction,
        private readonly WorkflowSignalAction $signalAction,
        private readonly WorkflowRetryAction $retryAction,
        private readonly WorkflowCancelAction $cancelAction,
        private readonly WorkflowStatsAction $statsAction,
    ) {}

    public function list(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->listAction)($request);
    }

    public function detail(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->detailAction)($request);
    }

    public function events(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->eventsAction)($request);
    }

    public function signal(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->signalAction)($request);
    }

    public function retry(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->retryAction)($request);
    }

    public function cancel(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->cancelAction)($request);
    }

    public function stats(Request $request): Response
    {
        if (!$this->guard->check($request)) {
            return $this->guard->deny();
        }

        return ($this->statsAction)($request);
    }
}

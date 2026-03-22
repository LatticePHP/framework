<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

/**
 * Internal exception thrown when replay catches up to the end of event history.
 * This signals the WorkflowRuntime to switch to live execution mode.
 */
final class ReplayCaughtUpException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Replay caught up to end of event history');
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

enum WorkflowStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Terminated = 'terminated';
    case TimedOut = 'timed_out';
}

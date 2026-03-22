<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

enum WorkflowEventType: string
{
    case WorkflowStarted = 'workflow_started';
    case WorkflowCompleted = 'workflow_completed';
    case WorkflowFailed = 'workflow_failed';
    case WorkflowCancelled = 'workflow_cancelled';
    case WorkflowTerminated = 'workflow_terminated';
    case ActivityScheduled = 'activity_scheduled';
    case ActivityStarted = 'activity_started';
    case ActivityCompleted = 'activity_completed';
    case ActivityFailed = 'activity_failed';
    case ActivityTimedOut = 'activity_timed_out';
    case TimerStarted = 'timer_started';
    case TimerFired = 'timer_fired';
    case TimerCancelled = 'timer_cancelled';
    case SignalReceived = 'signal_received';
    case QueryReceived = 'query_received';
    case UpdateReceived = 'update_received';
    case ChildWorkflowStarted = 'child_workflow_started';
    case ChildWorkflowCompleted = 'child_workflow_completed';
    case ChildWorkflowFailed = 'child_workflow_failed';
}

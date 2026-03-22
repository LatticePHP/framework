<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow]
final class TimerWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): string
    {
        $ctx->sleep($input['seconds'] ?? 10);
        $result = $ctx->executeActivity(PaymentActivity::class, 'charge', 100.0);
        return 'timer_done_' . $result;
    }
}

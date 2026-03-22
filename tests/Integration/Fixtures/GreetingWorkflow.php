<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

use Lattice\Workflow\Attributes\QueryMethod;
use Lattice\Workflow\Attributes\SignalMethod;
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow(name: 'GreetingWorkflow')]
final class GreetingWorkflow
{
    private string $status = 'started';

    public function execute(WorkflowContext $ctx, string $name): string
    {
        $this->status = 'greeting';

        $greeting = $ctx->executeActivity(
            GreetingActivity::class,
            'compose',
            $name,
        );

        $this->status = 'farewell';

        $farewell = $ctx->executeActivity(
            GreetingActivity::class,
            'farewell',
            $name,
        );

        $this->status = 'completed';

        return $greeting . ' ' . $farewell;
    }

    #[QueryMethod]
    public function getStatus(): string
    {
        return $this->status;
    }

    #[SignalMethod]
    public function markReviewed(): void
    {
        $this->status = 'reviewed';
    }
}

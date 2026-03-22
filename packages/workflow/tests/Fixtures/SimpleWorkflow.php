<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow(name: 'SimpleWorkflow')]
final class SimpleWorkflow
{
    public function execute(WorkflowContext $ctx): string
    {
        return 'simple_result';
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

final class WorkflowCancelledException extends \RuntimeException
{
    public function __construct(string $workflowId)
    {
        parent::__construct("Workflow cancelled: {$workflowId}");
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Workflow\Registry;

use Lattice\Workflow\Attributes\Activity;
use Lattice\Workflow\Attributes\Workflow;
use ReflectionClass;
use RuntimeException;

final class WorkflowRegistry
{
    /** @var array<string, string> workflow type name => class */
    private array $workflows = [];

    /** @var array<string, string> activity type name => class */
    private array $activities = [];

    public function registerWorkflow(string $class): void
    {
        $ref = new ReflectionClass($class);
        $attrs = $ref->getAttributes(Workflow::class);

        if (empty($attrs)) {
            throw new RuntimeException("Class {$class} is not annotated with #[Workflow]");
        }

        /** @var Workflow $attr */
        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $ref->getShortName();

        $this->workflows[$name] = $class;
    }

    public function registerActivity(string $class): void
    {
        $ref = new ReflectionClass($class);
        $attrs = $ref->getAttributes(Activity::class);

        if (empty($attrs)) {
            throw new RuntimeException("Class {$class} is not annotated with #[Activity]");
        }

        /** @var Activity $attr */
        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $ref->getShortName();

        $this->activities[$name] = $class;
    }

    public function getWorkflowClass(string $type): string
    {
        if (!isset($this->workflows[$type])) {
            throw new RuntimeException("Workflow type not registered: {$type}");
        }

        return $this->workflows[$type];
    }

    public function getActivityClass(string $type): string
    {
        if (!isset($this->activities[$type])) {
            throw new RuntimeException("Activity type not registered: {$type}");
        }

        return $this->activities[$type];
    }

    public function hasWorkflow(string $type): bool
    {
        return isset($this->workflows[$type]);
    }

    public function hasActivity(string $type): bool
    {
        return isset($this->activities[$type]);
    }

    /** @return array<string, string> */
    public function getRegisteredWorkflows(): array
    {
        return $this->workflows;
    }

    /** @return array<string, string> */
    public function getRegisteredActivities(): array
    {
        return $this->activities;
    }
}

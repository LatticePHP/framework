<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy;

final class Deployer
{
    /** @var list<DeployStep> */
    private array $steps = [];

    /** @var list<DeployStep> */
    private array $completedSteps = [];

    /** @var null|callable(string, string): void */
    private $onStepStart = null;

    /** @var null|callable(string, bool): void */
    private $onStepComplete = null;

    /**
     * Add a step to the deployment pipeline.
     */
    public function addStep(DeployStep $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * @param callable(string, string): void $callback Receives (step name, step description)
     */
    public function onStepStart(callable $callback): self
    {
        $this->onStepStart = $callback;

        return $this;
    }

    /**
     * @param callable(string, bool): void $callback Receives (step name, success)
     */
    public function onStepComplete(callable $callback): self
    {
        $this->onStepComplete = $callback;

        return $this;
    }

    /**
     * Execute all deployment steps sequentially.
     */
    public function deploy(): DeploymentResult
    {
        $this->completedSteps = [];
        $errors = [];
        $startTime = microtime(true);

        foreach ($this->steps as $step) {
            if ($this->onStepStart !== null) {
                ($this->onStepStart)($step->name(), $step->name());
            }

            try {
                $step->execute();
                $this->completedSteps[] = $step;

                if ($this->onStepComplete !== null) {
                    ($this->onStepComplete)($step->name(), true);
                }
            } catch (\Throwable $e) {
                $errors[] = $step->name() . ': ' . $e->getMessage();

                if ($this->onStepComplete !== null) {
                    ($this->onStepComplete)($step->name(), false);
                }

                $duration = microtime(true) - $startTime;

                return new DeploymentResult(
                    success: false,
                    completedSteps: array_map(
                        fn(DeployStep $s): string => $s->name(),
                        $this->completedSteps,
                    ),
                    duration: $duration,
                    errors: $errors,
                );
            }
        }

        $duration = microtime(true) - $startTime;

        return new DeploymentResult(
            success: true,
            completedSteps: array_map(
                fn(DeployStep $s): string => $s->name(),
                $this->completedSteps,
            ),
            duration: $duration,
        );
    }

    /**
     * Roll back all completed steps in reverse order.
     *
     * @return list<string> Names of rolled-back steps
     */
    public function rollback(): array
    {
        $rolledBack = [];
        $reversed = array_reverse($this->completedSteps);

        foreach ($reversed as $step) {
            try {
                $step->rollback();
                $rolledBack[] = $step->name();
            } catch (\Throwable) {
                // Best-effort rollback: continue even if a step fails to roll back
                $rolledBack[] = $step->name() . ' (partial)';
            }
        }

        return $rolledBack;
    }

    /**
     * @return list<DeployStep>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return list<DeployStep>
     */
    public function getCompletedSteps(): array
    {
        return $this->completedSteps;
    }
}

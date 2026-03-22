<?php

declare(strict_types=1);

namespace Lattice\Scheduler;

final class ScheduleEntry
{
    private CronExpression $cronExpression;
    private bool $preventOverlapping = false;

    private function __construct(
        private readonly string $type,
        private readonly mixed $task,
        private readonly string $name,
    ) {
        // Default: every minute
        $this->cronExpression = CronExpression::parse('* * * * *');
    }

    public static function forCallable(callable $task, ?string $name = null): self
    {
        $name ??= 'callable:' . spl_object_id((object) $task);

        return new self('callable', $task, $name);
    }

    public static function forCommand(string $command): self
    {
        return new self('command', $command, 'command:' . $command);
    }

    public static function forJob(string $jobClass): self
    {
        return new self('job', $jobClass, 'job:' . $jobClass);
    }

    public function everyMinute(): self
    {
        $this->cronExpression = CronExpression::parse('* * * * *');

        return $this;
    }

    public function everyFiveMinutes(): self
    {
        $this->cronExpression = CronExpression::parse('*/5 * * * *');

        return $this;
    }

    public function everyFifteenMinutes(): self
    {
        $this->cronExpression = CronExpression::parse('*/15 * * * *');

        return $this;
    }

    public function everyThirtyMinutes(): self
    {
        $this->cronExpression = CronExpression::parse('*/30 * * * *');

        return $this;
    }

    public function hourly(): self
    {
        $this->cronExpression = CronExpression::parse('0 * * * *');

        return $this;
    }

    public function daily(): self
    {
        $this->cronExpression = CronExpression::parse('0 0 * * *');

        return $this;
    }

    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time, 2);
        $this->cronExpression = CronExpression::parse(sprintf('%d %d * * *', (int) $minute, (int) $hour));

        return $this;
    }

    public function weekly(): self
    {
        $this->cronExpression = CronExpression::parse('0 0 * * 0');

        return $this;
    }

    public function weeklyOn(int $day, string $time): self
    {
        [$hour, $minute] = explode(':', $time, 2);
        $this->cronExpression = CronExpression::parse(sprintf('%d %d * * %d', (int) $minute, (int) $hour, $day));

        return $this;
    }

    public function monthly(): self
    {
        $this->cronExpression = CronExpression::parse('0 0 1 * *');

        return $this;
    }

    public function cron(string $expression): self
    {
        $this->cronExpression = CronExpression::parse($expression);

        return $this;
    }

    public function withoutOverlapping(): self
    {
        $this->preventOverlapping = true;

        return $this;
    }

    public function isDue(\DateTimeInterface $now): bool
    {
        return $this->cronExpression->isDue($now);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTask(): mixed
    {
        return $this->task;
    }

    public function isWithoutOverlapping(): bool
    {
        return $this->preventOverlapping;
    }

    /**
     * Execute the task and return the result.
     */
    public function execute(): mixed
    {
        if ($this->type === 'callable') {
            return ($this->task)();
        }

        // For command and job types, return the task identifier
        // Actual execution would be handled by the framework
        return $this->task;
    }
}

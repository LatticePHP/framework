<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class WorkflowGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'workflow';
    }

    public function getDescription(): string
    {
        return 'Creates workflow and activity classes with attributes';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Workflow name is required');
        /** @var string[] $activities */
        $activities = $options['activities'] ?? [];

        $files = [];

        // Workflow class
        $activityCalls = $this->generateActivityCalls($activities);
        $files[] = new GeneratedFile(
            path: 'app/Workflows/' . $name . 'Workflow.php',
            content: $this->template->render($this->workflowTemplate(), [
                'className' => $name . 'Workflow',
                'workflowName' => $name,
                'activityCalls' => $activityCalls,
            ]),
        );

        // Activity classes
        foreach ($activities as $activity) {
            $files[] = new GeneratedFile(
                path: 'app/Activities/' . $activity . 'Activity.php',
                content: $this->template->render($this->activityTemplate(), [
                    'className' => $activity . 'Activity',
                    'activityName' => $activity,
                ]),
            );
        }

        // Test file
        $files[] = new GeneratedFile(
            path: 'tests/Workflows/' . $name . 'WorkflowTest.php',
            content: $this->template->render($this->testTemplate(), [
                'className' => $name . 'WorkflowTest',
                'workflowClass' => $name . 'Workflow',
            ]),
        );

        return $files;
    }

    /**
     * @param string[] $activities
     */
    private function generateActivityCalls(array $activities): string
    {
        if ($activities === []) {
            return '        // Add activity calls here';
        }

        $lines = [];
        foreach ($activities as $activity) {
            $varName = lcfirst($activity);
            $lines[] = "        \$context->executeActivity({$activity}Activity::class, '{$varName}');";
        }
        return implode("\n", $lines);
    }

    private function workflowTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Workflows;

use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow(name: '{{workflowName}}')]
final class {{className}}
{
    public function execute(WorkflowContext $context): mixed
    {
{{activityCalls}}

        return ['status' => 'completed'];
    }
}
PHP;
    }

    private function activityTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Activities;

use Lattice\Workflow\Attributes\Activity;

#[Activity(name: '{{activityName}}')]
final class {{className}}
{
    public function execute(mixed $input = null): mixed
    {
        // Implement {{activityName}} logic
        return ['result' => '{{activityName}} completed'];
    }
}
PHP;
    }

    private function testTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Tests\Workflows;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class {{className}} extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->markTestIncomplete('Implement {{workflowClass}} tests');
    }
}
PHP;
    }
}

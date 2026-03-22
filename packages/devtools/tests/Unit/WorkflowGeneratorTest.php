<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\Generator\WorkflowGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowGeneratorTest extends TestCase
{
    private WorkflowGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new WorkflowGenerator();
    }

    #[Test]
    public function it_has_name_and_description(): void
    {
        $this->assertSame('workflow', $this->generator->getName());
        $this->assertNotEmpty($this->generator->getDescription());
    }

    #[Test]
    public function it_generates_workflow_class_with_attribute(): void
    {
        $files = $this->generator->generate([
            'name' => 'OrderFulfillment',
            'activities' => ['ProcessPayment', 'ShipOrder'],
        ]);

        $this->assertNotEmpty($files);

        $workflowFile = $this->findFileByPath($files, 'app/Workflows/OrderFulfillmentWorkflow.php');
        $this->assertNotNull($workflowFile);
        $this->assertSame('created', $workflowFile->type);
        $this->assertStringContainsString('declare(strict_types=1)', $workflowFile->content);
        $this->assertStringContainsString('#[Workflow(', $workflowFile->content);
        $this->assertStringContainsString('final class OrderFulfillmentWorkflow', $workflowFile->content);
    }

    #[Test]
    public function it_generates_activity_classes(): void
    {
        $files = $this->generator->generate([
            'name' => 'OrderFulfillment',
            'activities' => ['ProcessPayment', 'ShipOrder'],
        ]);

        $paymentFile = $this->findFileByPath($files, 'app/Activities/ProcessPaymentActivity.php');
        $this->assertNotNull($paymentFile, 'ProcessPayment activity should be generated');
        $this->assertStringContainsString('#[Activity(', $paymentFile->content);
        $this->assertStringContainsString('final class ProcessPaymentActivity', $paymentFile->content);

        $shipFile = $this->findFileByPath($files, 'app/Activities/ShipOrderActivity.php');
        $this->assertNotNull($shipFile, 'ShipOrder activity should be generated');
        $this->assertStringContainsString('#[Activity(', $shipFile->content);
    }

    #[Test]
    public function it_generates_test_file(): void
    {
        $files = $this->generator->generate([
            'name' => 'OrderFulfillment',
            'activities' => ['ProcessPayment'],
        ]);

        $testFile = $this->findFileByPath($files, 'tests/Workflows/OrderFulfillmentWorkflowTest.php');
        $this->assertNotNull($testFile, 'Test file should be generated');
        $this->assertStringContainsString('OrderFulfillmentWorkflowTest', $testFile->content);
    }

    /**
     * @param GeneratedFile[] $files
     */
    private function findFileByPath(array $files, string $path): ?GeneratedFile
    {
        foreach ($files as $file) {
            if ($file->path === $path) {
                return $file;
            }
        }
        return null;
    }
}

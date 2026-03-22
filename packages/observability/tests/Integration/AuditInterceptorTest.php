<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use Lattice\Observability\Audit\Attributes\AuditAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the AuditAction attribute.
 */
final class AuditInterceptorTest extends TestCase
{
    #[Test]
    public function test_audit_action_attribute_stores_description(): void
    {
        $attr = new AuditAction('Created a new invoice');
        $this->assertSame('Created a new invoice', $attr->description);
        $this->assertNull($attr->category);
    }

    #[Test]
    public function test_audit_action_attribute_stores_category(): void
    {
        $attr = new AuditAction('Deleted user account', category: 'admin');
        $this->assertSame('Deleted user account', $attr->description);
        $this->assertSame('admin', $attr->category);
    }

    #[Test]
    public function test_audit_action_is_target_method_attribute(): void
    {
        $reflection = new \ReflectionClass(AuditAction::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_METHOD, $attrInstance->flags);
    }
}

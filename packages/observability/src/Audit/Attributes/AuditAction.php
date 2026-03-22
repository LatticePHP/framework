<?php

declare(strict_types=1);

namespace Lattice\Observability\Audit\Attributes;

use Attribute;

/**
 * Mark a controller method for audit logging.
 *
 * When applied to a controller method, the AuditInterceptor will
 * automatically log the action after the method executes.
 *
 * Usage:
 *
 *     #[AuditAction('Created new invoice', category: 'billing')]
 *     public function create(CreateInvoiceDto $dto): Response { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class AuditAction
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $category = null,
    ) {}
}

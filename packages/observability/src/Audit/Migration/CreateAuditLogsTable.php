<?php

declare(strict_types=1);

namespace Lattice\Observability\Audit\Migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the audit_logs table for tracking model changes and user actions.
 */
final class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id')->nullable()->index();
            $table->string('action', 50)->index();
            $table->string('auditable_type')->index();
            $table->string('auditable_id')->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            // Composite index for polymorphic lookups
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');

            // Composite index for time-range queries per user
            $table->index(['user_id', 'created_at'], 'audit_logs_user_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
}

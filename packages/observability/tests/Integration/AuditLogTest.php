<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Lattice\Observability\Audit\AuditLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the AuditLog model, query helpers, and configuration.
 */
final class AuditLogTest extends TestCase
{
    private static bool $booted = false;

    protected function setUp(): void
    {
        if (!class_exists(Capsule::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }

        if (!self::$booted) {
            $capsule = new Capsule();
            $capsule->addConnection([
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]);
            $capsule->setEventDispatcher(new Dispatcher());
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            // Create the audit_logs table
            $capsule->getConnection()->getSchemaBuilder()->create('audit_logs', function ($table): void {
                $table->id();
                $table->string('user_id')->nullable();
                $table->string('action', 50);
                $table->string('auditable_type');
                $table->string('auditable_id');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('url')->nullable();
                $table->string('method', 10)->nullable();
                $table->timestamp('created_at')->nullable();
            });

            self::$booted = true;
        }

        // Clean up between tests
        AuditLog::query()->delete();
    }

    // ── Model Configuration ────────────────────────────────────────

    #[Test]
    public function test_audit_log_has_correct_table_name(): void
    {
        $log = new AuditLog();
        $this->assertSame('audit_logs', $log->getTable());
    }

    #[Test]
    public function test_audit_log_has_no_timestamps(): void
    {
        $log = new AuditLog();
        $this->assertFalse($log->usesTimestamps());
    }

    #[Test]
    public function test_audit_log_fillable_fields(): void
    {
        $log = new AuditLog();
        $expected = [
            'user_id',
            'action',
            'auditable_type',
            'auditable_id',
            'old_values',
            'new_values',
            'ip_address',
            'user_agent',
            'url',
            'method',
            'created_at',
        ];
        $this->assertSame($expected, $log->getFillable());
    }

    #[Test]
    public function test_audit_log_casts_json_columns(): void
    {
        $log = new AuditLog();
        $casts = $log->getCasts();

        $this->assertSame('array', $casts['old_values']);
        $this->assertSame('array', $casts['new_values']);
        $this->assertSame('datetime', $casts['created_at']);
    }

    // ── Create Audit Log Entry ─────────────────────────────────────

    #[Test]
    public function test_create_audit_log_for_model_created(): void
    {
        AuditLog::create([
            'user_id' => '5',
            'action' => 'created',
            'auditable_type' => 'App\\Models\\Order',
            'auditable_id' => '99',
            'old_values' => null,
            'new_values' => ['status' => 'active', 'total' => 100],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'TestAgent/1.0',
            'url' => '/api/orders',
            'method' => 'POST',
        ]);

        $this->assertDatabaseHasAuditEntry('created', 'App\\Models\\Order', '99');
    }

    #[Test]
    public function test_create_audit_log_for_model_updated(): void
    {
        AuditLog::create([
            'user_id' => '5',
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\Order',
            'auditable_id' => '99',
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'active'],
        ]);

        $entry = AuditLog::first();
        $this->assertNotNull($entry);
        $this->assertSame('updated', $entry->action);
        $this->assertSame(['status' => 'draft'], $entry->old_values);
        $this->assertSame(['status' => 'active'], $entry->new_values);
    }

    #[Test]
    public function test_create_audit_log_for_model_deleted(): void
    {
        AuditLog::create([
            'user_id' => '5',
            'action' => 'deleted',
            'auditable_type' => 'App\\Models\\Order',
            'auditable_id' => '99',
            'old_values' => ['status' => 'active', 'total' => 100],
            'new_values' => null,
        ]);

        $this->assertDatabaseHasAuditEntry('deleted', 'App\\Models\\Order', '99');
    }

    // ── Query Helpers ──────────────────────────────────────────────

    #[Test]
    public function test_for_user_returns_entries_for_specific_user(): void
    {
        AuditLog::create([
            'user_id' => '42',
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '1',
        ]);
        AuditLog::create([
            'user_id' => '99',
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '2',
        ]);

        $entries = AuditLog::forUser('42')->get();
        $this->assertCount(1, $entries);
        $this->assertSame('42', $entries[0]->user_id);
    }

    #[Test]
    public function test_for_model_returns_entries_for_specific_model_instance(): void
    {
        AuditLog::create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '7',
        ]);
        AuditLog::create([
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '7',
        ]);
        AuditLog::create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '8',
        ]);

        $entries = AuditLog::forModel('App\\Models\\User', '7')->get();
        $this->assertCount(2, $entries);
    }

    #[Test]
    public function test_between_returns_entries_in_date_range(): void
    {
        AuditLog::create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '1',
            'created_at' => '2025-06-01 00:00:00',
        ]);
        AuditLog::create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '2',
            'created_at' => '2025-07-15 00:00:00',
        ]);
        AuditLog::create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => '3',
            'created_at' => '2025-09-01 00:00:00',
        ]);

        $entries = AuditLog::between(
            new DateTimeImmutable('2025-06-01'),
            new DateTimeImmutable('2025-08-01'),
        )->get();

        $this->assertCount(2, $entries);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function assertDatabaseHasAuditEntry(string $action, string $type, string $id): void
    {
        $entry = AuditLog::where('action', $action)
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->first();

        $this->assertNotNull($entry, "Expected audit entry: action={$action}, type={$type}, id={$id}");
    }
}

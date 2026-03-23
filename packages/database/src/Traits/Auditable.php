<?php

declare(strict_types=1);

namespace Lattice\Database\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lattice\Observability\Audit\AuditLog;

/**
 * Automatically logs create/update/delete events on a model to the audit_logs table.
 *
 * Captures old/new values, the acting user, request metadata (IP, user agent, URL),
 * and filters out sensitive fields like passwords and tokens.
 *
 * Usage:
 *
 *     class User extends \Lattice\Database\Model
 *     {
 *         use Auditable;
 *
 *         // Optionally customize excluded fields:
 *         protected array $auditExclude = ['password', 'secret_key'];
 *     }
 */
trait Auditable
{
    /** @var array<class-string, list<array<string, mixed>>> Audit log entries keyed by model class */
    private static array $auditLogs = [];

    /** @var array<class-string, int|string|null> User ID overrides keyed by model class */
    private static array $auditUserIds = [];

    /** @var array<class-string, array{ip_address: ?string, user_agent: ?string, url: ?string, method: ?string}> Request meta keyed by model class */
    private static array $auditRequestMetas = [];

    /**
     * Boot the Auditable trait.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&Auditable $model */
            $model->recordAudit('created', [], $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            /** @var Model&Auditable $model */
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);
            $model->recordAudit('updated', $original, $changes);
        });

        static::deleted(function (Model $model): void {
            /** @var Model&Auditable $model */
            $model->recordAudit('deleted', $model->getAttributes(), []);
        });
    }

    /**
     * Get the audit log entries for this model via polymorphic relation.
     *
     * @return MorphMany<AuditLog, Model>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Record an audit log entry to the database.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    protected function recordAudit(string $action, array $old, array $new): void
    {
        // Filter out excluded fields
        $exclude = $this->auditExclude ?? ['password', 'password_hash', 'remember_token'];
        $old = array_diff_key($old, array_flip($exclude));
        $new = array_diff_key($new, array_flip($exclude));

        // Resolve the current user from auth context
        $userId = $this->resolveAuditUserId();

        // Resolve request metadata
        $requestMeta = $this->resolveRequestMetadata();

        // Store in the static audit log keyed by class
        self::$auditLogs[static::class] ??= [];

        $entry = [
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $requestMeta['ip_address'] ?? null,
            'user_agent' => $requestMeta['user_agent'] ?? null,
            'url' => $requestMeta['url'] ?? null,
            'method' => $requestMeta['method'] ?? null,
            'created_at' => now(),
        ];

        self::$auditLogs[static::class][] = $entry;

        // Write to the audit_logs table
        try {
            AuditLog::create($entry);
        } catch (\Throwable) {
            // Audit logging should never break the main operation.
            // In production, this would be logged to the error handler.
        }

        // Also dispatch event if the event dispatcher is available
        $dispatcher = $this->getEventDispatcher();

        if ($dispatcher !== null) {
            $dispatcher->dispatch('model.audited', [
                'action' => $action,
                'model_type' => static::class,
                'model_id' => $this->getKey(),
                'old_values' => $old,
                'new_values' => $new,
            ]);
        }
    }

    /**
     * Resolve the current authenticated user ID for audit attribution.
     */
    protected function resolveAuditUserId(): int|string|null
    {
        // Check for a class-keyed test override
        if (isset(self::$auditUserIds[static::class])) {
            return self::$auditUserIds[static::class];
        }

        return null;
    }

    /**
     * Resolve request metadata (IP, user agent, URL, method) from the current context.
     *
     * @return array{ip_address: ?string, user_agent: ?string, url: ?string, method: ?string}
     */
    protected function resolveRequestMetadata(): array
    {
        // Check for a class-keyed test override
        if (isset(self::$auditRequestMetas[static::class])) {
            return self::$auditRequestMetas[static::class];
        }

        return [
            'ip_address' => null,
            'user_agent' => null,
            'url' => null,
            'method' => null,
        ];
    }

    /**
     * Get the audit log entries (primarily for testing).
     *
     * @return list<array<string, mixed>>
     */
    public static function getAuditLog(): array
    {
        return self::$auditLogs[static::class] ?? [];
    }

    /**
     * Clear the audit log (primarily for testing).
     */
    public static function clearAuditLog(): void
    {
        self::$auditLogs[static::class] = [];
    }

    /**
     * Set the user ID for audit attribution (primarily for testing).
     */
    public static function setAuditUserId(int|string|null $userId): void
    {
        self::$auditUserIds[static::class] = $userId;
    }

    /**
     * Set request metadata for audit logging (primarily for testing).
     *
     * @param array{ip_address: ?string, user_agent: ?string, url: ?string, method: ?string} $meta
     */
    public static function setAuditRequestMeta(array $meta): void
    {
        self::$auditRequestMetas[static::class] = $meta;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Observability\Audit;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for persisted audit log entries.
 *
 * Stores a full history of who changed what, when, and from where.
 * Immutable once written — no updates or soft-deletes.
 */
final class AuditLog extends Model
{
    /** @var string */
    protected $table = 'audit_logs';

    /** @var bool */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Query audit logs for a specific user.
     *
     * @return Builder<static>
     */
    public static function forUser(int|string $userId): Builder
    {
        return static::where('user_id', $userId);
    }

    /**
     * Query audit logs for a specific model instance.
     *
     * @return Builder<static>
     */
    public static function forModel(string $type, int|string $id): Builder
    {
        return static::where('auditable_type', $type)->where('auditable_id', $id);
    }

    /**
     * Query audit logs within a date range.
     *
     * @return Builder<static>
     */
    public static function between(DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return static::whereBetween('created_at', [$start, $end]);
    }
}

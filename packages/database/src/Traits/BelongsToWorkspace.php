<?php

declare(strict_types=1);

namespace Lattice\Database\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Automatically scopes queries to the current workspace and sets workspace_id on create.
 *
 * Usage:
 *
 *     class Project extends Model
 *     {
 *         use BelongsToWorkspace;
 *     }
 *
 * Override getWorkspaceColumn() to customize the column name.
 * Override resolveCurrentWorkspaceId() to change how the current workspace is resolved.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        // Auto-scope queries to current workspace
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $workspaceId = static::resolveCurrentWorkspaceId();

            if ($workspaceId !== null) {
                $builder->where(static::getWorkspaceColumn(), $workspaceId);
            }
        });

        // Auto-set workspace_id on create
        // NOTE: We use a captured class reference because static:: inside a static
        // closure registered via Eloquent events does not late-static-bind to the
        // model class; it resolves to the trait-user's class at definition time
        // which may differ when Eloquent fires the event from the base Model.
        $callingClass = static::class;

        static::creating(static function (mixed $model) use ($callingClass): void {
            // Resolve workspace ID via the model class's own method
            $workspaceId = null;
            if (property_exists($callingClass, 'testWorkspaceId') && isset($callingClass::$testWorkspaceId)) {
                $workspaceId = $callingClass::$testWorkspaceId;
            } elseif (class_exists(\Lattice\Auth\Workspace\WorkspaceContext::class)) {
                $workspaceId = \Lattice\Auth\Workspace\WorkspaceContext::id();
            }

            $column = $callingClass::getWorkspaceColumn();

            if ($workspaceId !== null && !$model->{$column}) {
                $model->{$column} = $workspaceId;
            }
        });
    }

    /**
     * Get the workspace foreign key column name.
     */
    public static function getWorkspaceColumn(): string
    {
        return 'workspace_id';
    }

    /**
     * Resolve the current workspace ID from the application context.
     *
     * Uses WorkspaceContext (set by WorkspaceGuard during request lifecycle).
     * Falls back to a static test override when set.
     */
    protected static function resolveCurrentWorkspaceId(): ?int
    {
        // Check for a static override (useful for testing)
        if (property_exists(static::class, 'testWorkspaceId') && isset(static::$testWorkspaceId)) {
            return static::$testWorkspaceId;
        }

        // Resolve from WorkspaceContext (set by WorkspaceGuard)
        if (class_exists(\Lattice\Auth\Workspace\WorkspaceContext::class)) {
            return \Lattice\Auth\Workspace\WorkspaceContext::id();
        }

        return null;
    }
}

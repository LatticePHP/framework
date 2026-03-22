<?php

declare(strict_types=1);

namespace Lattice\Auth\Workspace;

use Lattice\Auth\Models\Workspace;

/**
 * Static context holder for the current workspace.
 *
 * Set during request lifecycle by WorkspaceGuard or middleware.
 * Read by BelongsToWorkspace trait for automatic scoping.
 */
final class WorkspaceContext
{
    private static ?Workspace $current = null;

    /**
     * Set the current workspace for this request/process.
     */
    public static function set(?Workspace $workspace): void
    {
        self::$current = $workspace;
    }

    /**
     * Get the current workspace, or null if none is set.
     */
    public static function get(): ?Workspace
    {
        return self::$current;
    }

    /**
     * Get the current workspace ID, or null if none is set.
     */
    public static function id(): ?int
    {
        return self::$current?->id;
    }

    /**
     * Reset the workspace context (e.g., at end of request).
     */
    public static function reset(): void
    {
        self::$current = null;
    }

    /**
     * Check if a workspace context is currently active.
     */
    public static function isActive(): bool
    {
        return self::$current !== null;
    }
}

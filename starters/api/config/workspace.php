<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Workspace Mode
    |--------------------------------------------------------------------------
    | Enable/disable workspace feature. When disabled, all resources are global.
    */
    'enabled' => (bool) env('WORKSPACE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Workspace Model
    |--------------------------------------------------------------------------
    */
    'model' => env('WORKSPACE_MODEL', \App\Models\Workspace::class),

    /*
    |--------------------------------------------------------------------------
    | Workspace Resolution
    |--------------------------------------------------------------------------
    | How the current workspace is determined:
    |
    | "header"   — X-Workspace-Id header
    | "jwt"      — workspace_id claim in JWT token
    | "url"      — /workspaces/{id}/... URL prefix
    | "session"  — Last selected workspace (for stateful apps)
    */
    'resolver' => env('WORKSPACE_RESOLVER', 'header'),

    /*
    |--------------------------------------------------------------------------
    | Workspace Column Name
    |--------------------------------------------------------------------------
    | Column name used in workspace-scoped models
    */
    'column' => env('WORKSPACE_COLUMN', 'workspace_id'),

    /*
    |--------------------------------------------------------------------------
    | Workspace Roles
    |--------------------------------------------------------------------------
    | Roles available within a workspace
    */
    'roles' => [
        'owner' => [
            'label' => 'Owner',
            'permissions' => ['*'],  // All permissions
        ],
        'admin' => [
            'label' => 'Admin',
            'permissions' => [
                'workspace.settings',
                'workspace.members.manage',
                'workspace.invitations.manage',
                'resources.*',
            ],
        ],
        'member' => [
            'label' => 'Member',
            'permissions' => [
                'resources.create',
                'resources.read',
                'resources.update',
                'resources.delete.own',  // Only own resources
            ],
        ],
        'viewer' => [
            'label' => 'Viewer',
            'permissions' => [
                'resources.read',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_members' => (int) env('WORKSPACE_MAX_MEMBERS', 50),
        'max_workspaces_per_user' => (int) env('WORKSPACE_MAX_PER_USER', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Invitations
    |--------------------------------------------------------------------------
    */
    'invitations' => [
        'expires_days' => (int) env('WORKSPACE_INVITE_EXPIRES', 7),
        'max_pending' => (int) env('WORKSPACE_MAX_PENDING_INVITES', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Propagation
    |--------------------------------------------------------------------------
    */
    'propagate' => [
        'queue' => true,
        'events' => true,
        'workflow' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Workspace Settings
    |--------------------------------------------------------------------------
    | Applied when a new workspace is created
    */
    'defaults' => [
        'timezone' => env('WORKSPACE_DEFAULT_TIMEZONE', 'UTC'),
        'currency' => env('WORKSPACE_DEFAULT_CURRENCY', 'USD'),
        'locale' => env('WORKSPACE_DEFAULT_LOCALE', 'en'),
        'date_format' => env('WORKSPACE_DEFAULT_DATE_FORMAT', 'Y-m-d'),
    ],
];

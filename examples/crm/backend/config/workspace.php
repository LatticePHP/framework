<?php

declare(strict_types=1);

return [
    'strategy' => env('WORKSPACE_STRATEGY', 'header'),
    'header' => env('WORKSPACE_HEADER', 'X-Workspace-Id'),
];

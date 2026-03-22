<?php

declare(strict_types=1);

namespace Lattice\Auth\Workspace;

use Lattice\Auth\Models\User;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;

/**
 * Pipeline guard that resolves the current workspace and verifies membership.
 *
 * Usage:
 *     #[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
 *
 * The guard:
 * 1. Resolves the workspace from the request (strategy from config)
 * 2. Verifies the authenticated user is a member of that workspace
 * 3. Sets WorkspaceContext for downstream use
 */
final class WorkspaceGuard implements GuardInterface
{
    public function __construct(
        private readonly WorkspaceResolver $resolver,
        private readonly string $strategy = 'header',
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        // Resolve the workspace from the request
        $request = $this->getRequest($context);

        if ($request === null) {
            return false;
        }

        $workspace = $this->resolver->resolve($request, $this->strategy);

        if ($workspace === null) {
            return false;
        }

        // Verify the authenticated user is a member of this workspace
        $principal = $context->getPrincipal();

        if ($principal !== null) {
            $user = User::find($principal->getId());

            if ($user === null || !$workspace->hasMember($user)) {
                return false;
            }
        }

        // Set the workspace context for the remainder of this request
        WorkspaceContext::set($workspace);

        return true;
    }

    /**
     * Extract the request from the execution context.
     */
    private function getRequest(ExecutionContextInterface $context): mixed
    {
        if (method_exists($context, 'getRequest')) {
            return $context->getRequest();
        }

        return null;
    }
}

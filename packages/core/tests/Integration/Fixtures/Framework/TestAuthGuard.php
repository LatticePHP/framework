<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Auth\Principal;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Http\HttpExecutionContext;

final class TestAuthGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        if (!$context instanceof HttpExecutionContext) {
            return false;
        }

        $token = $context->getRequest()->bearerToken();

        if ($token === 'valid-token') {
            $context->setPrincipal(new Principal(id: '1', type: 'user'));
            return true;
        }

        return false;
    }
}

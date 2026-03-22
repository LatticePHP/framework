<?php

declare(strict_types=1);

namespace Lattice\Testing\Traits;

use Lattice\Testing\Fakes\FakePrincipal;

/**
 * Automatically creates a fake authenticated user before each test.
 *
 * After setUp, `$this->authenticatedUser` contains a FakePrincipal
 * and the test case is configured to act as that user.
 */
trait WithAuthentication
{
    protected ?FakePrincipal $authenticatedUser = null;

    protected function setUpWithAuthentication(): void
    {
        $this->authenticatedUser = $this->createAuthenticatedUser();
        $this->actingAs($this->authenticatedUser);
    }

    protected function tearDownWithAuthentication(): void
    {
        $this->authenticatedUser = null;
    }

    /**
     * Create the default authenticated user for tests.
     *
     * Override to customize the user attributes.
     */
    protected function createAuthenticatedUser(): FakePrincipal
    {
        return new FakePrincipal(
            id: 'test-user-' . bin2hex(random_bytes(4)),
            type: 'user',
            scopes: [],
            roles: ['user'],
        );
    }
}

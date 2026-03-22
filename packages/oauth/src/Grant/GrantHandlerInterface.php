<?php

declare(strict_types=1);

namespace Lattice\OAuth\Grant;

use Lattice\OAuth\ClientInterface;
use Lattice\OAuth\TokenResponse;

interface GrantHandlerInterface
{
    public function supports(string $grantType): bool;

    public function handle(array $params, ClientInterface $client): TokenResponse;
}

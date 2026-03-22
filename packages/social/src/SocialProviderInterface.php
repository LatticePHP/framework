<?php

declare(strict_types=1);

namespace Lattice\Social;

interface SocialProviderInterface
{
    public function getName(): string;

    public function getRedirectUrl(string $state): string;

    public function handleCallback(array $params): SocialUser;
}

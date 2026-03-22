<?php

declare(strict_types=1);

namespace Lattice\Social\Testing;

use Lattice\Social\SocialProviderInterface;
use Lattice\Social\SocialUser;

final class FakeSocialProvider implements SocialProviderInterface
{
    public function __construct(
        private readonly string $name,
        private readonly SocialUser $user,
        private readonly string $redirectUrl = 'https://fake-provider.test/authorize',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUrl(string $state): string
    {
        $separator = str_contains($this->redirectUrl, '?') ? '&' : '?';

        return $this->redirectUrl . $separator . http_build_query(['state' => $state]);
    }

    public function handleCallback(array $params): SocialUser
    {
        return $this->user;
    }
}

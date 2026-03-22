<?php

declare(strict_types=1);

namespace Lattice\Social;

final class SocialAuthManager
{
    /** @var array<string, SocialProviderInterface> */
    private array $providers = [];

    public function registerProvider(string $name, SocialProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function getRedirectUrl(string $provider, string $state): string
    {
        return $this->getProvider($provider)->getRedirectUrl($state);
    }

    public function handleCallback(string $provider, array $params): SocialUser
    {
        return $this->getProvider($provider)->handleCallback($params);
    }

    public function getProvider(string $name): SocialProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("Unknown social provider: {$name}");
        }

        return $this->providers[$name];
    }
}

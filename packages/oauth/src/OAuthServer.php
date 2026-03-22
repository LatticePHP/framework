<?php

declare(strict_types=1);

namespace Lattice\OAuth;

use Lattice\OAuth\Grant\GrantHandlerInterface;

final class OAuthServer
{
    /** @var array<GrantHandlerInterface> */
    private readonly array $grantHandlers;

    /**
     * @param array<GrantHandlerInterface> $grantHandlers
     */
    public function __construct(
        private readonly ClientStoreInterface $clientStore,
        array $grantHandlers,
    ) {
        $this->grantHandlers = $grantHandlers;
    }

    public function handleTokenRequest(array $params): TokenResponse
    {
        $grantType = $params['grant_type'] ?? throw new \InvalidArgumentException('Missing grant_type');

        $clientId = $params['client_id'] ?? null;
        $clientSecret = $params['client_secret'] ?? null;

        if ($clientId === null || $clientSecret === null) {
            throw new \InvalidArgumentException('Missing client credentials');
        }

        if (!$this->clientStore->validateSecret($clientId, $clientSecret)) {
            throw new \InvalidArgumentException('Invalid client credentials');
        }

        $client = $this->clientStore->find($clientId);

        $supportedHandler = null;
        foreach ($this->grantHandlers as $handler) {
            if ($handler->supports($grantType)) {
                $supportedHandler = $handler;
                break;
            }
        }

        if ($supportedHandler === null) {
            throw new \InvalidArgumentException("Unsupported grant type: {$grantType}");
        }

        if (!in_array($grantType, $client->getGrantTypes(), true)) {
            throw new \InvalidArgumentException("Grant type not allowed for this client: {$grantType}");
        }

        return $supportedHandler->handle($params, $client);
    }
}

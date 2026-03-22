<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;

final class CapabilityNegotiator
{
    private const string PROTOCOL_VERSION = '2024-11-05';
    private const string SERVER_NAME = 'lattice-mcp';
    private const string SERVER_VERSION = '1.0.0';

    /** @var array<string, mixed> */
    private array $clientCapabilities = [];

    /** @var array<string, mixed> */
    private array $clientInfo = [];

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly PromptRegistry $promptRegistry,
        private readonly string $serverName = self::SERVER_NAME,
        private readonly string $serverVersion = self::SERVER_VERSION,
    ) {}

    /**
     * Process initialize params and produce server capabilities.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function initialize(array $params): array
    {
        $this->clientCapabilities = $params['capabilities'] ?? [];
        $this->clientInfo = $params['clientInfo'] ?? [];

        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => $this->buildServerCapabilities(),
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => $this->serverVersion,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientCapabilities(): array
    {
        return $this->clientCapabilities;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientInfo(): array
    {
        return $this->clientInfo;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildServerCapabilities(): array
    {
        $capabilities = [];

        if ($this->toolRegistry->count() > 0) {
            $capabilities['tools'] = ['listChanged' => false];
        }

        if ($this->resourceRegistry->count() > 0) {
            $capabilities['resources'] = ['subscribe' => false, 'listChanged' => false];
        }

        if ($this->promptRegistry->count() > 0) {
            $capabilities['prompts'] = ['listChanged' => false];
        }

        return $capabilities;
    }
}

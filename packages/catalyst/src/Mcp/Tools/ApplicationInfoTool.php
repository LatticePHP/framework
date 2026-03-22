<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class ApplicationInfoTool implements McpToolInterface
{
    /**
     * @param array<string, mixed> $appInfo
     */
    public function __construct(
        private readonly array $appInfo = [],
    ) {}

    public function getName(): string
    {
        return 'app_info';
    }

    public function getDescription(): string
    {
        return 'Returns PHP version, LatticePHP framework version, installed packages, and registered modules';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'framework' => 'LatticePHP',
            'framework_version' => $this->appInfo['framework_version'] ?? '1.0.0',
            'environment' => $this->appInfo['environment'] ?? 'production',
            'debug' => $this->appInfo['debug'] ?? false,
            'packages' => $this->appInfo['packages'] ?? [],
            'modules' => $this->appInfo['modules'] ?? [],
        ];
    }
}

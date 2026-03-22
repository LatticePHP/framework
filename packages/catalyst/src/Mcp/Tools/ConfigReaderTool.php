<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class ConfigReaderTool implements McpToolInterface
{
    /** @var list<string> Keys that indicate sensitive values to be redacted */
    private const array SENSITIVE_PATTERNS = [
        'password',
        'secret',
        'key',
        'token',
        'api_key',
        'apikey',
        'private',
        'credential',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function getName(): string
    {
        return 'config_reader';
    }

    public function getDescription(): string
    {
        return 'Read framework configuration values by key with dot notation. Sensitive values (passwords, secrets, API keys) are redacted by default.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'key' => [
                    'type' => 'string',
                    'description' => 'Config key in dot notation (e.g., "database.default", "cache.driver"). Omit to list all top-level keys.',
                ],
                'show_sensitive' => [
                    'type' => 'boolean',
                    'description' => 'Show sensitive values without redaction (default: false)',
                    'default' => false,
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $key = $arguments['key'] ?? null;
        $showSensitive = (bool) ($arguments['show_sensitive'] ?? false);

        if (!is_string($key) || $key === '') {
            // Return all top-level config keys
            return [
                'keys' => array_keys($this->config),
                'total' => count($this->config),
            ];
        }

        $value = $this->getNestedValue($key);

        if ($value === null) {
            return [
                'key' => $key,
                'value' => null,
                'found' => false,
            ];
        }

        if (!$showSensitive) {
            $value = $this->redactSensitive($value, $key);
        }

        return [
            'key' => $key,
            'value' => $value,
            'found' => true,
        ];
    }

    private function getNestedValue(string $key): mixed
    {
        $segments = explode('.', $key);
        $current = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function redactSensitive(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $k => $v) {
                $fullKey = $key . '.' . $k;
                $redacted[$k] = $this->redactSensitive($v, $fullKey);
            }

            return $redacted;
        }

        if (is_string($value) && $this->isSensitiveKey($key)) {
            return '********';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($lowerKey, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

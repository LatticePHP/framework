<?php

declare(strict_types=1);

namespace Lattice\Ai\Config;

final readonly class AiConfig
{
    /**
     * @param array<string, array<string, mixed>> $providers Per-provider configuration
     * @param float $temperature Global default temperature
     * @param int $maxTokens Global default max tokens
     * @param float $topP Global default top-p
     * @param list<string> $stopSequences Global default stop sequences
     */
    public function __construct(
        public string $defaultProvider = 'openai',
        public array $providers = [],
        public float $temperature = 0.7,
        public int $maxTokens = 1024,
        public float $topP = 1.0,
        public array $stopSequences = [],
    ) {}

    /**
     * Get config for a specific provider.
     *
     * @return array<string, mixed>
     */
    public function providerConfig(string $name): array
    {
        return $this->providers[$name] ?? [];
    }

    /**
     * Get API key for a provider, masking it in debug output.
     */
    public function apiKey(string $provider): string
    {
        return (string) ($this->providers[$provider]['api_key'] ?? '');
    }

    /**
     * Get base URL for a provider.
     */
    public function baseUrl(string $provider): string
    {
        return (string) ($this->providers[$provider]['base_url'] ?? '');
    }

    /**
     * Get default model for a provider.
     */
    public function defaultModel(string $provider): string
    {
        return (string) ($this->providers[$provider]['model'] ?? '');
    }

    /**
     * Get timeout for a provider.
     */
    public function timeout(string $provider): int
    {
        return (int) ($this->providers[$provider]['timeout'] ?? 30);
    }

    /**
     * Create from a config array (e.g. from config/ai.php).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            defaultProvider: (string) ($config['default'] ?? 'openai'),
            providers: (array) ($config['providers'] ?? []),
            temperature: (float) ($config['temperature'] ?? 0.7),
            maxTokens: (int) ($config['max_tokens'] ?? 1024),
            topP: (float) ($config['top_p'] ?? 1.0),
            stopSequences: (array) ($config['stop_sequences'] ?? []),
        );
    }

    /**
     * Mask an API key for safe debug output.
     */
    public static function maskApiKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
}

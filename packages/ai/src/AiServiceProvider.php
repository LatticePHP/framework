<?php

declare(strict_types=1);

namespace Lattice\Ai;

use Lattice\Ai\Config\AiConfig;
use Lattice\Ai\Providers\AnthropicProvider;
use Lattice\Ai\Providers\CohereProvider;
use Lattice\Ai\Providers\GeminiProvider;
use Lattice\Ai\Providers\OllamaProvider;
use Lattice\Ai\Providers\OpenAiProvider;
use Lattice\Ai\Providers\ProviderInterface;

final class AiServiceProvider
{
    private ?AiConfig $config = null;
    private ?AiManager $manager = null;

    /**
     * @param array<string, mixed> $configArray Raw configuration array from config/ai.php
     */
    public function __construct(
        private readonly array $configArray = [],
    ) {}

    /**
     * Register AI services.
     */
    public function register(): AiManager
    {
        $this->config = AiConfig::fromArray($this->configArray);
        $this->manager = new AiManager($this->config->defaultProvider);

        // Lazily register all v1.0 providers
        $this->registerProvider('anthropic', fn (): ProviderInterface => new AnthropicProvider($this->config()));
        $this->registerProvider('openai', fn (): ProviderInterface => new OpenAiProvider($this->config()));
        $this->registerProvider('gemini', fn (): ProviderInterface => new GeminiProvider($this->config()));
        $this->registerProvider('ollama', fn (): ProviderInterface => new OllamaProvider($this->config()));
        $this->registerProvider('cohere', fn (): ProviderInterface => new CohereProvider($this->config()));

        return $this->manager;
    }

    /**
     * Get the AI config instance.
     */
    public function config(): AiConfig
    {
        if ($this->config === null) {
            $this->config = AiConfig::fromArray($this->configArray);
        }

        return $this->config;
    }

    /**
     * Register a provider in the manager, creating it eagerly for now.
     *
     * @param callable(): ProviderInterface $factory
     */
    private function registerProvider(string $name, callable $factory): void
    {
        // Only register if there's config for this provider or it's the default
        if ($this->manager === null) {
            return;
        }

        $this->manager->register($name, $factory());
    }
}

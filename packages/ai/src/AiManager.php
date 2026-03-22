<?php

declare(strict_types=1);

namespace Lattice\Ai;

use Generator;
use Lattice\Ai\Exceptions\ProviderNotFoundException;
use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Providers\ProviderInterface;
use Lattice\Ai\Responses\AiResponse;
use Lattice\Ai\Responses\StreamChunk;
use Lattice\Ai\Testing\FakeProvider;

final class AiManager
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    private string $defaultProvider;

    public function __construct(string $defaultProvider = 'openai')
    {
        $this->defaultProvider = $defaultProvider;
    }

    /**
     * Register a provider by name.
     */
    public function register(string $name, ProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get a provider by name.
     *
     * @throws ProviderNotFoundException
     */
    public function provider(string $name): ProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw ProviderNotFoundException::forName($name);
        }

        return $this->providers[$name];
    }

    /**
     * Get the default provider.
     *
     * @throws ProviderNotFoundException
     */
    public function getDefaultProvider(): ProviderInterface
    {
        return $this->provider($this->defaultProvider);
    }

    /**
     * Switch to a different default provider at runtime.
     */
    public function using(string $provider): self
    {
        $clone = clone $this;
        $clone->defaultProvider = $provider;

        return $clone;
    }

    /**
     * Send a chat completion request using the default provider.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResponse
    {
        return $this->getDefaultProvider()->chat($messages, $options);
    }

    /**
     * Send a streaming chat completion request using the default provider.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     * @return Generator<int, StreamChunk>
     */
    public function stream(array $messages, array $options = []): Generator
    {
        return $this->getDefaultProvider()->stream($messages, $options);
    }

    /**
     * Replace all registered providers with FakeProvider instances for testing.
     */
    public function fake(): FakeProvider
    {
        $fake = new FakeProvider();

        // Replace all registered providers with the fake
        foreach (array_keys($this->providers) as $name) {
            $this->providers[$name] = $fake;
        }

        // Also register it under the default provider name
        $this->providers[$this->defaultProvider] = $fake;

        return $fake;
    }

    /**
     * Get all registered provider names.
     *
     * @return list<string>
     */
    public function registeredProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if a provider is registered.
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}

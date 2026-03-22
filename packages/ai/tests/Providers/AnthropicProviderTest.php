<?php

declare(strict_types=1);

namespace Lattice\Ai\Tests\Providers;

use Lattice\Ai\Config\AiConfig;
use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolCall;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Providers\AnthropicProvider;
use Lattice\Ai\Providers\ProviderCapability;
use Lattice\Ai\Responses\FinishReason;
use Lattice\HttpClient\HttpClientResponse;
use Lattice\HttpClient\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnthropicProviderTest extends TestCase
{
    private function createProvider(?FakeHttpClient $httpClient = null): AnthropicProvider
    {
        $config = new AiConfig(
            defaultProvider: 'anthropic',
            providers: [
                'anthropic' => [
                    'api_key' => 'test-key-anthropic',
                    'base_url' => 'https://api.anthropic.test',
                    'model' => 'claude-sonnet-4-20250514',
                    'timeout' => 30,
                    'max_retries' => 0,
                ],
            ],
        );

        return new AnthropicProvider($config, $httpClient);
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());
        $this->assertSame('anthropic', $provider->name());
    }

    #[Test]
    public function it_supports_expected_capabilities(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $this->assertTrue($provider->supports(ProviderCapability::Chat));
        $this->assertTrue($provider->supports(ProviderCapability::Streaming));
        $this->assertTrue($provider->supports(ProviderCapability::ToolCalling));
        $this->assertTrue($provider->supports(ProviderCapability::Vision));
        $this->assertFalse($provider->supports(ProviderCapability::Embeddings));
        $this->assertFalse($provider->supports(ProviderCapability::ImageGeneration));
    }

    #[Test]
    public function it_builds_correct_request_body(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $messages = [
            SystemMessage::create('You are helpful.'),
            UserMessage::create('Hello'),
        ];

        $body = $provider->buildRequestBody($messages, ['temperature' => 0.5]);

        $this->assertSame('claude-sonnet-4-20250514', $body['model']);
        $this->assertSame('You are helpful.', $body['system']);
        $this->assertCount(1, $body['messages']);
        $this->assertSame('user', $body['messages'][0]['role']);
        $this->assertSame('Hello', $body['messages'][0]['content']);
        $this->assertSame(0.5, $body['temperature']);
        $this->assertSame(1024, $body['max_tokens']);
    }

    #[Test]
    public function it_builds_request_body_with_tool_calls(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $messages = [
            UserMessage::create('What is the weather?'),
            AssistantMessage::withToolCalls('', [
                new ToolCall('tc_1', 'getWeather', ['city' => 'London']),
            ]),
            new ToolResult('tc_1', '{"temp": 22}'),
        ];

        $body = $provider->buildRequestBody($messages);

        $this->assertCount(3, $body['messages']);
        // Assistant message with tool_use blocks
        $assistantMsg = $body['messages'][1];
        $this->assertSame('assistant', $assistantMsg['role']);
        $this->assertIsArray($assistantMsg['content']);
        $this->assertSame('tool_use', $assistantMsg['content'][0]['type']);
        // Tool result
        $toolMsg = $body['messages'][2];
        $this->assertSame('user', $toolMsg['role']);
        $this->assertSame('tool_result', $toolMsg['content'][0]['type']);
    }

    #[Test]
    public function it_builds_request_body_with_vision_content(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $messages = [
            UserMessage::withImages('Describe this', ['https://example.com/image.jpg']),
        ];

        $body = $provider->buildRequestBody($messages);

        $content = $body['messages'][0]['content'];
        $this->assertIsArray($content);
        $this->assertSame('text', $content[0]['type']);
        $this->assertSame('image', $content[1]['type']);
        $this->assertSame('url', $content[1]['source']['type']);
    }

    #[Test]
    public function it_parses_chat_response(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $rawResponse = [
            'id' => 'msg_123',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Hello! How can I help?'],
            ],
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 15,
                'output_tokens' => 25,
            ],
        ];

        $response = $provider->parseResponse($rawResponse);

        $this->assertSame('Hello! How can I help?', $response->getText());
        $this->assertSame(15, $response->getUsage()->promptTokens);
        $this->assertSame(25, $response->getUsage()->completionTokens);
        $this->assertSame(40, $response->getUsage()->totalTokens);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertFalse($response->hasToolCalls());
        $this->assertTrue($response->isComplete());
    }

    #[Test]
    public function it_parses_tool_use_response(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $rawResponse = [
            'id' => 'msg_456',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Let me check the weather.'],
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01',
                    'name' => 'getWeather',
                    'input' => ['city' => 'London'],
                ],
            ],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 20, 'output_tokens' => 30],
        ];

        $response = $provider->parseResponse($rawResponse);

        $this->assertSame('Let me check the weather.', $response->getText());
        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->getToolCalls());
        $this->assertSame('getWeather', $response->getToolCalls()[0]->name);
        $this->assertSame(['city' => 'London'], $response->getToolCalls()[0]->arguments);
        $this->assertSame(FinishReason::ToolCall, $response->finishReason);
    }

    #[Test]
    public function it_parses_max_tokens_finish_reason(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $rawResponse = [
            'content' => [['type' => 'text', 'text' => 'Truncated...']],
            'stop_reason' => 'max_tokens',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 100],
        ];

        $response = $provider->parseResponse($rawResponse);
        $this->assertSame(FinishReason::Length, $response->finishReason);
        $this->assertFalse($response->isComplete());
    }

    #[Test]
    public function it_makes_chat_request_via_http(): void
    {
        $httpClient = new FakeHttpClient();
        $httpClient->stub(
            'https://api.anthropic.test/v1/messages',
            new HttpClientResponse(200, [], json_encode([
                'content' => [['type' => 'text', 'text' => 'Mocked response']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 5, 'output_tokens' => 10],
                'model' => 'claude-sonnet-4-20250514',
            ])),
        );

        $provider = $this->createProvider($httpClient);
        $response = $provider->chat([UserMessage::create('Test')]);

        $this->assertSame('Mocked response', $response->getText());
        $httpClient->assertSent('https://api.anthropic.test/v1/messages');
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Ai\Tests\Providers;

use Lattice\Ai\Config\AiConfig;
use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolCall;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Providers\OpenAiProvider;
use Lattice\Ai\Providers\ProviderCapability;
use Lattice\Ai\Responses\FinishReason;
use Lattice\HttpClient\HttpClientResponse;
use Lattice\HttpClient\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenAiProviderTest extends TestCase
{
    private function createProvider(?FakeHttpClient $httpClient = null): OpenAiProvider
    {
        $config = new AiConfig(
            defaultProvider: 'openai',
            providers: [
                'openai' => [
                    'api_key' => 'test-key-openai',
                    'base_url' => 'https://api.openai.test',
                    'model' => 'gpt-4o',
                    'timeout' => 30,
                    'max_retries' => 0,
                ],
            ],
        );

        return new OpenAiProvider($config, $httpClient);
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());
        $this->assertSame('openai', $provider->name());
    }

    #[Test]
    public function it_supports_expected_capabilities(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $this->assertTrue($provider->supports(ProviderCapability::Chat));
        $this->assertTrue($provider->supports(ProviderCapability::Streaming));
        $this->assertTrue($provider->supports(ProviderCapability::ToolCalling));
        $this->assertTrue($provider->supports(ProviderCapability::Embeddings));
        $this->assertTrue($provider->supports(ProviderCapability::ImageGeneration));
        $this->assertTrue($provider->supports(ProviderCapability::AudioSynthesis));
        $this->assertTrue($provider->supports(ProviderCapability::AudioTranscription));
        $this->assertFalse($provider->supports(ProviderCapability::Reranking));
    }

    #[Test]
    public function it_builds_correct_request_body(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $messages = [
            SystemMessage::create('You are helpful.'),
            UserMessage::create('Hello'),
        ];

        $body = $provider->buildRequestBody($messages, [
            'temperature' => 0.5,
            'max_tokens' => 500,
        ]);

        $this->assertSame('gpt-4o', $body['model']);
        $this->assertCount(2, $body['messages']);
        $this->assertSame('system', $body['messages'][0]['role']);
        $this->assertSame('user', $body['messages'][1]['role']);
        $this->assertSame(0.5, $body['temperature']);
        $this->assertSame(500, $body['max_tokens']);
    }

    #[Test]
    public function it_formats_tool_calls_in_request(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $messages = [
            UserMessage::create('What time is it?'),
        ];

        $body = $provider->buildRequestBody($messages, [
            'tools' => [
                [
                    'name' => 'getTime',
                    'description' => 'Get the current time',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
        ]);

        $this->assertArrayHasKey('tools', $body);
        $this->assertSame('function', $body['tools'][0]['type']);
        $this->assertSame('getTime', $body['tools'][0]['function']['name']);
    }

    #[Test]
    public function it_formats_assistant_message_with_tool_calls(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $toolCall = new ToolCall('tc_1', 'getWeather', ['city' => 'NYC']);
        $message = AssistantMessage::withToolCalls('', [$toolCall]);
        $formatted = $provider->formatMessage($message);

        $this->assertSame('assistant', $formatted['role']);
        $this->assertCount(1, $formatted['tool_calls']);
        $this->assertSame('tc_1', $formatted['tool_calls'][0]['id']);
        $this->assertSame('function', $formatted['tool_calls'][0]['type']);
        $this->assertSame('getWeather', $formatted['tool_calls'][0]['function']['name']);
    }

    #[Test]
    public function it_formats_tool_result_message(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $result = new ToolResult('tc_1', '{"temp": 22}');
        $formatted = $provider->formatMessage($result);

        $this->assertSame('tool', $formatted['role']);
        $this->assertSame('tc_1', $formatted['tool_call_id']);
        $this->assertSame('{"temp": 22}', $formatted['content']);
    }

    #[Test]
    public function it_parses_chat_response(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $rawResponse = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
            ],
        ];

        $response = $provider->parseResponse($rawResponse);

        $this->assertSame('Hello! How can I help you?', $response->getText());
        $this->assertSame(10, $response->getUsage()->promptTokens);
        $this->assertSame(20, $response->getUsage()->completionTokens);
        $this->assertSame(30, $response->getUsage()->totalTokens);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertFalse($response->hasToolCalls());
    }

    #[Test]
    public function it_parses_tool_calls_response(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $rawResponse = [
            'id' => 'chatcmpl-456',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'getWeather',
                                    'arguments' => '{"city":"London"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 10],
        ];

        $response = $provider->parseResponse($rawResponse);

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->getToolCalls());
        $this->assertSame('getWeather', $response->getToolCalls()[0]->name);
        $this->assertSame(['city' => 'London'], $response->getToolCalls()[0]->arguments);
        $this->assertSame(FinishReason::ToolCall, $response->finishReason);
    }

    #[Test]
    public function it_parses_content_filter_finish_reason(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());

        $rawResponse = [
            'choices' => [
                [
                    'message' => ['content' => ''],
                    'finish_reason' => 'content_filter',
                ],
            ],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 0],
        ];

        $response = $provider->parseResponse($rawResponse);
        $this->assertSame(FinishReason::ContentFilter, $response->finishReason);
    }

    #[Test]
    public function it_makes_chat_request_via_http(): void
    {
        $httpClient = new FakeHttpClient();
        $httpClient->stub(
            'https://api.openai.test/v1/chat/completions',
            new HttpClientResponse(200, [], json_encode([
                'id' => 'chatcmpl-test',
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Mocked!'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 5],
            ])),
        );

        $provider = $this->createProvider($httpClient);
        $response = $provider->chat([UserMessage::create('Test')]);

        $this->assertSame('Mocked!', $response->getText());
        $httpClient->assertSent('https://api.openai.test/v1/chat/completions');
    }

    #[Test]
    public function it_builds_vision_content(): void
    {
        $provider = $this->createProvider(new FakeHttpClient());
        $message = UserMessage::withImages('What is this?', ['https://img.test/photo.jpg']);

        $formatted = $provider->formatMessage($message);

        $this->assertSame('user', $formatted['role']);
        $this->assertIsArray($formatted['content']);
        $this->assertSame('text', $formatted['content'][0]['type']);
        $this->assertSame('image_url', $formatted['content'][1]['type']);
        $this->assertSame('https://img.test/photo.jpg', $formatted['content'][1]['image_url']['url']);
    }
}

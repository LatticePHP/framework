<?php

declare(strict_types=1);

namespace Lattice\Ai\Providers;

use Generator;
use Lattice\Ai\Config\AiConfig;
use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolCall;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Responses\AiResponse;
use Lattice\Ai\Responses\FinishReason;
use Lattice\Ai\Responses\StreamChunk;
use Lattice\Ai\Responses\Usage;
use Lattice\HttpClient\HttpClient;

final class OpenAiProvider extends AbstractProvider
{
    private const string DEFAULT_BASE_URL = 'https://api.openai.com';
    private const string DEFAULT_MODEL = 'gpt-4o';

    public function __construct(AiConfig $config, ?HttpClient $httpClient = null)
    {
        parent::__construct($config, $httpClient);

        if ($this->baseUrl === '') {
            $this->baseUrl = self::DEFAULT_BASE_URL;
        }

        if ($this->defaultModel === '') {
            $this->defaultModel = self::DEFAULT_MODEL;
        }
    }

    public function name(): string
    {
        return 'openai';
    }

    /**
     * @return list<ProviderCapability>
     */
    public function capabilities(): array
    {
        return [
            ProviderCapability::Chat,
            ProviderCapability::Streaming,
            ProviderCapability::ToolCalling,
            ProviderCapability::StructuredOutput,
            ProviderCapability::Embeddings,
            ProviderCapability::ImageGeneration,
            ProviderCapability::AudioSynthesis,
            ProviderCapability::AudioTranscription,
            ProviderCapability::Vision,
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $body = $this->buildRequestBody($messages, $options);
        $response = $this->sendRequest('POST', '/v1/chat/completions', $body);
        $data = $response->json();

        return $this->parseResponse($data);
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $body = $this->buildRequestBody($messages, $options);
        $body['stream'] = true;
        $response = $this->sendRequest('POST', '/v1/chat/completions', $body);

        $events = $this->parseSSE($response->body());

        foreach ($events as $event) {
            if ($event['data'] === '[DONE]') {
                yield new StreamChunk('', true, FinishReason::Stop);
                return;
            }

            $data = json_decode($event['data'], true);
            if (!is_array($data)) {
                continue;
            }

            $chunk = $this->parseStreamEvent($data);
            if ($chunk !== null) {
                yield $chunk;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->apiKey}",
        ];

        $providerConfig = $this->config->providerConfig('openai');
        if (isset($providerConfig['organization'])) {
            $headers['OpenAI-Organization'] = (string) $providerConfig['organization'];
        }

        return $headers;
    }

    /**
     * Build the API request body for the OpenAI Chat Completions API.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildRequestBody(array $messages, array $options = []): array
    {
        $body = [
            'model' => $this->resolveModel($options),
            'messages' => array_map([$this, 'formatMessage'], $messages),
        ];

        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $body['top_p'] = (float) $options['top_p'];
        }

        if (isset($options['tools']) && is_array($options['tools'])) {
            $body['tools'] = array_map(static function (array $tool): array {
                return [
                    'type' => 'function',
                    'function' => $tool,
                ];
            }, $options['tools']);
        }

        if (isset($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
        }

        if (isset($options['stop']) && is_array($options['stop'])) {
            $body['stop'] = $options['stop'];
        }

        return $body;
    }

    /**
     * Format a message for the OpenAI API.
     *
     * @return array<string, mixed>
     */
    public function formatMessage(UserMessage|AssistantMessage|SystemMessage|ToolResult $message): array
    {
        if ($message instanceof SystemMessage) {
            return ['role' => 'system', 'content' => $message->content];
        }

        if ($message instanceof UserMessage) {
            if (is_array($message->content)) {
                // Multipart content (vision)
                $content = [];
                foreach ($message->content as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $content[] = ['type' => 'text', 'text' => $part['text']];
                    } elseif (($part['type'] ?? '') === 'image_url') {
                        $content[] = [
                            'type' => 'image_url',
                            'image_url' => ['url' => $part['url']],
                        ];
                    }
                }

                return ['role' => 'user', 'content' => $content];
            }

            return ['role' => 'user', 'content' => $message->content];
        }

        if ($message instanceof AssistantMessage) {
            $formatted = ['role' => 'assistant', 'content' => $message->content];

            if ($message->hasToolCalls()) {
                $formatted['tool_calls'] = array_map(static function (ToolCall $tc): array {
                    return [
                        'id' => $tc->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $tc->name,
                            'arguments' => json_encode($tc->arguments),
                        ],
                    ];
                }, $message->toolCalls);
            }

            return $formatted;
        }

        // ToolResult
        return [
            'role' => 'tool',
            'tool_call_id' => $message->toolCallId,
            'content' => $message->content,
        ];
    }

    /**
     * Parse an OpenAI API response into an AiResponse.
     *
     * @param array<string, mixed> $data
     */
    public function parseResponse(array $data): AiResponse
    {
        $choice = $data['choices'][0] ?? [];
        $messageData = $choice['message'] ?? [];

        $content = (string) ($messageData['content'] ?? '');
        $toolCalls = [];

        if (isset($messageData['tool_calls']) && is_array($messageData['tool_calls'])) {
            foreach ($messageData['tool_calls'] as $tc) {
                $arguments = $tc['function']['arguments'] ?? '{}';
                $decoded = json_decode((string) $arguments, true);

                $toolCalls[] = new ToolCall(
                    (string) ($tc['id'] ?? ''),
                    (string) ($tc['function']['name'] ?? ''),
                    is_array($decoded) ? $decoded : [],
                );
            }
        }

        $usageData = $data['usage'] ?? [];
        $usage = new Usage(
            (int) ($usageData['prompt_tokens'] ?? 0),
            (int) ($usageData['completion_tokens'] ?? 0),
        );

        $finishReason = $this->mapFinishReason((string) ($choice['finish_reason'] ?? 'stop'));

        return new AiResponse(
            content: $content,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            model: (string) ($data['model'] ?? ''),
            rawResponse: $data,
        );
    }

    /**
     * Parse a stream event into a StreamChunk.
     *
     * @param array<string, mixed> $data
     */
    private function parseStreamEvent(array $data): ?StreamChunk
    {
        $choice = $data['choices'][0] ?? [];
        $delta = $choice['delta'] ?? [];
        $finishReason = $choice['finish_reason'] ?? null;

        if ($finishReason !== null) {
            return new StreamChunk(
                (string) ($delta['content'] ?? ''),
                true,
                $this->mapFinishReason((string) $finishReason),
            );
        }

        $content = $delta['content'] ?? null;
        if ($content !== null) {
            return new StreamChunk((string) $content);
        }

        return null;
    }

    /**
     * Map OpenAI finish_reason to FinishReason enum.
     */
    private function mapFinishReason(string $reason): FinishReason
    {
        return match ($reason) {
            'stop' => FinishReason::Stop,
            'length' => FinishReason::Length,
            'tool_calls', 'function_call' => FinishReason::ToolCall,
            'content_filter' => FinishReason::ContentFilter,
            default => FinishReason::Stop,
        };
    }
}

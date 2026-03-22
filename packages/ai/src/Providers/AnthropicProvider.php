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

final class AnthropicProvider extends AbstractProvider
{
    private const string DEFAULT_BASE_URL = 'https://api.anthropic.com';
    private const string DEFAULT_MODEL = 'claude-sonnet-4-20250514';
    private const string API_VERSION = '2023-06-01';

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
        return 'anthropic';
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
            ProviderCapability::Vision,
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $body = $this->buildRequestBody($messages, $options);
        $response = $this->sendRequest('POST', '/v1/messages', $body);
        $data = $response->json();

        return $this->parseResponse($data);
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $body = $this->buildRequestBody($messages, $options);
        $body['stream'] = true;
        $response = $this->sendRequest('POST', '/v1/messages', $body);

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
        return [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ];
    }

    /**
     * Build the API request body for the Anthropic Messages API.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildRequestBody(array $messages, array $options = []): array
    {
        $body = [
            'model' => $this->resolveModel($options),
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config->maxTokens),
        ];

        $system = null;
        $formatted = [];

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $system = $message->content;
                continue;
            }

            $formatted[] = $this->formatMessage($message);
        }

        if ($system !== null) {
            $body['system'] = $system;
        }

        $body['messages'] = $formatted;

        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $body['top_p'] = (float) $options['top_p'];
        }

        if (isset($options['tools']) && is_array($options['tools'])) {
            $body['tools'] = $options['tools'];
        }

        if (isset($options['stop_sequences']) && is_array($options['stop_sequences'])) {
            $body['stop_sequences'] = $options['stop_sequences'];
        }

        return $body;
    }

    /**
     * Format a message for the Anthropic API.
     *
     * @return array<string, mixed>
     */
    private function formatMessage(UserMessage|AssistantMessage|ToolResult $message): array
    {
        if ($message instanceof UserMessage) {
            if (is_array($message->content)) {
                // Multipart content (vision)
                $content = [];
                foreach ($message->content as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $content[] = ['type' => 'text', 'text' => $part['text']];
                    } elseif (($part['type'] ?? '') === 'image_url') {
                        $content[] = [
                            'type' => 'image',
                            'source' => [
                                'type' => 'url',
                                'url' => $part['url'],
                            ],
                        ];
                    }
                }

                return ['role' => 'user', 'content' => $content];
            }

            return ['role' => 'user', 'content' => $message->content];
        }

        if ($message instanceof AssistantMessage) {
            if ($message->hasToolCalls()) {
                $content = [];
                if ($message->content !== '') {
                    $content[] = ['type' => 'text', 'text' => $message->content];
                }
                foreach ($message->toolCalls as $toolCall) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $toolCall->id,
                        'name' => $toolCall->name,
                        'input' => $toolCall->arguments,
                    ];
                }

                return ['role' => 'assistant', 'content' => $content];
            }

            return ['role' => 'assistant', 'content' => $message->content];
        }

        // ToolResult
        return [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $message->toolCallId,
                    'content' => $message->content,
                    'is_error' => $message->isError,
                ],
            ],
        ];
    }

    /**
     * Parse an Anthropic API response into an AiResponse.
     *
     * @param array<string, mixed> $data
     */
    public function parseResponse(array $data): AiResponse
    {
        $content = '';
        $toolCalls = [];

        $contentBlocks = $data['content'] ?? [];
        if (is_array($contentBlocks)) {
            foreach ($contentBlocks as $block) {
                if (!is_array($block)) {
                    continue;
                }

                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'] ?? '';
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = new ToolCall(
                        $block['id'] ?? '',
                        $block['name'] ?? '',
                        (array) ($block['input'] ?? []),
                    );
                }
            }
        }

        $usageData = $data['usage'] ?? [];
        $usage = new Usage(
            (int) ($usageData['input_tokens'] ?? 0),
            (int) ($usageData['output_tokens'] ?? 0),
        );

        $finishReason = $this->mapStopReason((string) ($data['stop_reason'] ?? 'end_turn'));

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
        $type = $data['type'] ?? '';

        if ($type === 'content_block_delta') {
            $delta = $data['delta'] ?? [];
            if (is_array($delta) && ($delta['type'] ?? '') === 'text_delta') {
                return new StreamChunk($delta['text'] ?? '');
            }
        }

        if ($type === 'message_delta') {
            $stopReason = $data['delta']['stop_reason'] ?? null;
            if ($stopReason !== null) {
                return new StreamChunk('', true, $this->mapStopReason((string) $stopReason));
            }
        }

        if ($type === 'message_stop') {
            return new StreamChunk('', true, FinishReason::Stop);
        }

        return null;
    }

    /**
     * Map Anthropic stop_reason to FinishReason enum.
     */
    private function mapStopReason(string $reason): FinishReason
    {
        return match ($reason) {
            'end_turn', 'stop_sequence' => FinishReason::Stop,
            'max_tokens' => FinishReason::Length,
            'tool_use' => FinishReason::ToolCall,
            default => FinishReason::Stop,
        };
    }
}

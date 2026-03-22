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

final class CohereProvider extends AbstractProvider
{
    private const string DEFAULT_BASE_URL = 'https://api.cohere.com';
    private const string DEFAULT_MODEL = 'command-r-plus';

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
        return 'cohere';
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
            ProviderCapability::Embeddings,
            ProviderCapability::Reranking,
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $body = $this->buildRequestBody($messages, $options);
        $response = $this->sendRequest('POST', '/v2/chat', $body);
        $data = $response->json();

        return $this->parseResponse($data);
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $body = $this->buildRequestBody($messages, $options);
        $body['stream'] = true;
        $response = $this->sendRequest('POST', '/v2/chat', $body);

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
            'Authorization' => "Bearer {$this->apiKey}",
        ];
    }

    /**
     * Build the API request body for the Cohere v2 Chat API.
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

        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['top_p'])) {
            $body['p'] = (float) $options['top_p'];
        }

        if (isset($options['stop_sequences']) && is_array($options['stop_sequences'])) {
            $body['stop_sequences'] = $options['stop_sequences'];
        }

        if (isset($options['tools']) && is_array($options['tools'])) {
            $body['tools'] = array_map(static function (array $tool): array {
                return [
                    'type' => 'function',
                    'function' => $tool,
                ];
            }, $options['tools']);
        }

        return $body;
    }

    /**
     * Format a message for the Cohere v2 API.
     *
     * @return array<string, mixed>
     */
    public function formatMessage(UserMessage|AssistantMessage|SystemMessage|ToolResult $message): array
    {
        if ($message instanceof SystemMessage) {
            return ['role' => 'system', 'content' => $message->content];
        }

        if ($message instanceof UserMessage) {
            return ['role' => 'user', 'content' => is_string($message->content) ? $message->content : json_encode($message->content)];
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
     * Parse a Cohere API response into an AiResponse.
     *
     * @param array<string, mixed> $data
     */
    public function parseResponse(array $data): AiResponse
    {
        $content = (string) ($data['message']['content'][0]['text'] ?? '');

        $toolCalls = [];
        if (isset($data['message']['tool_calls']) && is_array($data['message']['tool_calls'])) {
            foreach ($data['message']['tool_calls'] as $tc) {
                $function = $tc['function'] ?? [];
                $arguments = $function['arguments'] ?? '{}';
                $decoded = json_decode((string) $arguments, true);

                $toolCalls[] = new ToolCall(
                    (string) ($tc['id'] ?? ''),
                    (string) ($function['name'] ?? ''),
                    is_array($decoded) ? $decoded : [],
                );
            }
        }

        $usageData = $data['usage'] ?? [];
        $billedUnits = $usageData['billed_units'] ?? [];
        $usage = new Usage(
            (int) ($billedUnits['input_tokens'] ?? 0),
            (int) ($billedUnits['output_tokens'] ?? 0),
        );

        $finishReason = $this->mapFinishReason((string) ($data['finish_reason'] ?? 'COMPLETE'));

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

        if ($type === 'content-delta') {
            $text = (string) ($data['delta']['message']['content']['text'] ?? '');

            return new StreamChunk($text);
        }

        if ($type === 'message-end') {
            return new StreamChunk('', true, FinishReason::Stop);
        }

        return null;
    }

    /**
     * Map Cohere finish reason to FinishReason enum.
     */
    private function mapFinishReason(string $reason): FinishReason
    {
        return match ($reason) {
            'COMPLETE' => FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'TOOL_CALL' => FinishReason::ToolCall,
            'ERROR' => FinishReason::Error,
            default => FinishReason::Stop,
        };
    }
}

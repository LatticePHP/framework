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

final class OllamaProvider extends AbstractProvider
{
    private const string DEFAULT_BASE_URL = 'http://localhost:11434';
    private const string DEFAULT_MODEL = 'llama3.2';

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
        return 'ollama';
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
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $body = $this->buildRequestBody($messages, $options);
        $body['stream'] = false;
        $response = $this->sendRequest('POST', '/api/chat', $body);
        $data = $response->json();

        return $this->parseResponse($data);
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $body = $this->buildRequestBody($messages, $options);
        $body['stream'] = true;
        $response = $this->sendRequest('POST', '/api/chat', $body);

        // Ollama returns newline-delimited JSON
        $lines = explode("\n", trim($response->body()));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                continue;
            }

            $done = (bool) ($data['done'] ?? false);
            $content = (string) ($data['message']['content'] ?? '');

            yield new StreamChunk(
                $content,
                $done,
                $done ? FinishReason::Stop : null,
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Build the API request body for the Ollama chat API.
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

        $ollamaOptions = [];

        if (isset($options['temperature'])) {
            $ollamaOptions['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $ollamaOptions['top_p'] = (float) $options['top_p'];
        }

        if (isset($options['max_tokens'])) {
            $ollamaOptions['num_predict'] = (int) $options['max_tokens'];
        }

        if (isset($options['stop']) && is_array($options['stop'])) {
            $ollamaOptions['stop'] = $options['stop'];
        }

        if ($ollamaOptions !== []) {
            $body['options'] = $ollamaOptions;
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
     * Format a message for the Ollama API.
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
            return ['role' => 'assistant', 'content' => $message->content];
        }

        // ToolResult
        return ['role' => 'tool', 'content' => $message->content];
    }

    /**
     * Parse an Ollama API response into an AiResponse.
     *
     * @param array<string, mixed> $data
     */
    public function parseResponse(array $data): AiResponse
    {
        $messageData = $data['message'] ?? [];
        $content = (string) ($messageData['content'] ?? '');

        $toolCalls = [];
        if (isset($messageData['tool_calls']) && is_array($messageData['tool_calls'])) {
            foreach ($messageData['tool_calls'] as $tc) {
                $function = $tc['function'] ?? [];
                $toolCalls[] = new ToolCall(
                    'call_' . md5((string) ($function['name'] ?? '')),
                    (string) ($function['name'] ?? ''),
                    (array) ($function['arguments'] ?? []),
                );
            }
        }

        // Ollama provides eval/prompt token counts
        $usage = new Usage(
            (int) ($data['prompt_eval_count'] ?? 0),
            (int) ($data['eval_count'] ?? 0),
        );

        $done = (bool) ($data['done'] ?? true);
        $doneReason = (string) ($data['done_reason'] ?? 'stop');

        $finishReason = match ($doneReason) {
            'stop' => FinishReason::Stop,
            'length' => FinishReason::Length,
            default => $done ? FinishReason::Stop : FinishReason::Stop,
        };

        return new AiResponse(
            content: $content,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            model: (string) ($data['model'] ?? ''),
            rawResponse: $data,
        );
    }
}

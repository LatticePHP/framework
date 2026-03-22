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

final class GeminiProvider extends AbstractProvider
{
    private const string DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com';
    private const string DEFAULT_MODEL = 'gemini-2.0-flash';

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
        return 'gemini';
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
            ProviderCapability::Vision,
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $model = $this->resolveModel($options);
        $body = $this->buildRequestBody($messages, $options);
        $response = $this->sendRequest('POST', "/v1/models/{$model}:generateContent?key={$this->apiKey}", $body);
        $data = $response->json();

        return $this->parseResponse($data);
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $model = $this->resolveModel($options);
        $body = $this->buildRequestBody($messages, $options);
        $response = $this->sendRequest('POST', "/v1/models/{$model}:streamGenerateContent?key={$this->apiKey}", $body);

        // Gemini streaming returns JSON array
        $data = json_decode($response->body(), true);
        if (!is_array($data)) {
            return;
        }

        // Handle both array of chunks and single response
        $chunks = isset($data[0]) ? $data : [$data];

        foreach ($chunks as $i => $chunk) {
            if (!is_array($chunk)) {
                continue;
            }

            $text = $this->extractTextFromCandidate($chunk);
            $isLast = $i === count($chunks) - 1;

            $finishReason = null;
            if ($isLast) {
                $candidate = $chunk['candidates'][0] ?? [];
                $reason = $candidate['finishReason'] ?? null;
                $finishReason = $reason !== null ? $this->mapFinishReason((string) $reason) : FinishReason::Stop;
            }

            yield new StreamChunk($text, $isLast, $finishReason);
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
     * Build the API request body for the Gemini generateContent API.
     *
     * @param list<UserMessage|AssistantMessage|SystemMessage|ToolResult> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildRequestBody(array $messages, array $options = []): array
    {
        $body = [];
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $systemInstruction = $message->content;
                continue;
            }

            $contents[] = $this->formatMessage($message);
        }

        $body['contents'] = $contents;

        if ($systemInstruction !== null) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $generationConfig = [];

        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['top_p'])) {
            $generationConfig['topP'] = (float) $options['top_p'];
        }

        if (isset($options['stop_sequences']) && is_array($options['stop_sequences'])) {
            $generationConfig['stopSequences'] = $options['stop_sequences'];
        }

        if ($generationConfig !== []) {
            $body['generationConfig'] = $generationConfig;
        }

        if (isset($options['tools']) && is_array($options['tools'])) {
            $body['tools'] = [
                [
                    'functionDeclarations' => $options['tools'],
                ],
            ];
        }

        return $body;
    }

    /**
     * Format a message for the Gemini API.
     *
     * @return array<string, mixed>
     */
    private function formatMessage(UserMessage|AssistantMessage|ToolResult $message): array
    {
        if ($message instanceof UserMessage) {
            if (is_array($message->content)) {
                $parts = [];
                foreach ($message->content as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $parts[] = ['text' => $part['text']];
                    } elseif (($part['type'] ?? '') === 'image_url') {
                        $parts[] = [
                            'inlineData' => [
                                'mimeType' => 'image/jpeg',
                                'data' => $part['url'],
                            ],
                        ];
                    }
                }

                return ['role' => 'user', 'parts' => $parts];
            }

            return ['role' => 'user', 'parts' => [['text' => $message->content]]];
        }

        if ($message instanceof AssistantMessage) {
            if ($message->hasToolCalls()) {
                $parts = [];
                if ($message->content !== '') {
                    $parts[] = ['text' => $message->content];
                }
                foreach ($message->toolCalls as $toolCall) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $toolCall->name,
                            'args' => $toolCall->arguments,
                        ],
                    ];
                }

                return ['role' => 'model', 'parts' => $parts];
            }

            return ['role' => 'model', 'parts' => [['text' => $message->content]]];
        }

        // ToolResult
        return [
            'role' => 'user',
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $message->toolCallId,
                        'response' => ['result' => $message->content],
                    ],
                ],
            ],
        ];
    }

    /**
     * Parse a Gemini API response into an AiResponse.
     *
     * @param array<string, mixed> $data
     */
    public function parseResponse(array $data): AiResponse
    {
        $content = $this->extractTextFromCandidate($data);
        $toolCalls = $this->extractToolCalls($data);

        $usageData = $data['usageMetadata'] ?? [];
        $usage = new Usage(
            (int) ($usageData['promptTokenCount'] ?? 0),
            (int) ($usageData['candidatesTokenCount'] ?? 0),
        );

        $candidate = $data['candidates'][0] ?? [];
        $finishReasonStr = (string) ($candidate['finishReason'] ?? 'STOP');
        $finishReason = $this->mapFinishReason($finishReasonStr);

        return new AiResponse(
            content: $content,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            model: (string) ($data['modelVersion'] ?? ''),
            rawResponse: $data,
        );
    }

    /**
     * Extract text from the first candidate.
     *
     * @param array<string, mixed> $data
     */
    private function extractTextFromCandidate(array $data): string
    {
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $text = '';

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        return $text;
    }

    /**
     * Extract tool calls from the first candidate.
     *
     * @param array<string, mixed> $data
     * @return list<ToolCall>
     */
    private function extractToolCalls(array $data): array
    {
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $toolCalls = [];

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $fc = $part['functionCall'];
                    $toolCalls[] = new ToolCall(
                        'call_' . md5((string) ($fc['name'] ?? '')),
                        (string) ($fc['name'] ?? ''),
                        (array) ($fc['args'] ?? []),
                    );
                }
            }
        }

        return $toolCalls;
    }

    /**
     * Map Gemini finish reason to FinishReason enum.
     */
    private function mapFinishReason(string $reason): FinishReason
    {
        return match ($reason) {
            'STOP' => FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY' => FinishReason::ContentFilter,
            'RECITATION' => FinishReason::ContentFilter,
            default => FinishReason::Stop,
        };
    }
}

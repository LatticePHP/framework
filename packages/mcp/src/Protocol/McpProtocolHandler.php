<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;

final class McpProtocolHandler
{
    private SessionState $state = SessionState::Connecting;
    private readonly JsonRpcServer $jsonRpc;
    private readonly CapabilityNegotiator $negotiator;

    /** @var array<string, true> */
    private array $cancelledRequests = [];

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceRegistry $resourceRegistry,
        private readonly PromptRegistry $promptRegistry,
        ?CapabilityNegotiator $negotiator = null,
        private readonly ?ToolExecutorInterface $toolExecutor = null,
        private readonly ?ResourceReaderInterface $resourceReader = null,
        private readonly ?PromptRendererInterface $promptRenderer = null,
        string $serverName = 'lattice-mcp',
        string $serverVersion = '1.0.0',
    ) {
        $this->jsonRpc = new JsonRpcServer();
        $this->negotiator = $negotiator ?? new CapabilityNegotiator(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $serverName,
            $serverVersion,
        );
    }

    /**
     * Process a raw JSON string and return a JSON response string.
     */
    public function processMessage(string $json): string
    {
        try {
            $parsed = $this->jsonRpc->parse($json);
        } catch (JsonRpcException $e) {
            return $this->jsonRpc->encode($e->toResponse());
        }

        if (is_array($parsed)) {
            $responses = [];

            foreach ($parsed as $request) {
                $response = $this->handleRequest($request);

                if ($response !== null) {
                    $responses[] = $response;
                }
            }

            return $responses === [] ? '' : $this->jsonRpc->encodeBatch($responses);
        }

        $response = $this->handleRequest($parsed);

        if ($response === null) {
            return '';
        }

        return $this->jsonRpc->encode($response);
    }

    public function getState(): SessionState
    {
        return $this->state;
    }

    public function isReady(): bool
    {
        return $this->state === SessionState::Ready;
    }

    private function handleRequest(JsonRpcRequest $request): ?JsonRpcResponse
    {
        // Handle notifications (no id)
        if ($request->isNotification()) {
            $this->handleNotification($request);

            return null;
        }

        // Before initialization, only allow 'initialize'
        if ($this->state === SessionState::Connecting && $request->method !== 'initialize') {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_REQUEST,
                'Server not initialized. Send "initialize" first.',
            );
        }

        // After shutdown, reject all
        if ($this->state === SessionState::ShuttingDown || $this->state === SessionState::Closed) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_REQUEST,
                'Server is shutting down.',
            );
        }

        return match ($request->method) {
            'initialize' => $this->handleInitialize($request),
            'ping' => JsonRpcResponse::success($request->id, []),
            'shutdown' => $this->handleShutdown($request),
            'tools/list' => $this->handleToolsList($request),
            'tools/call' => $this->handleToolsCall($request),
            'resources/list' => $this->handleResourcesList($request),
            'resources/templates/list' => $this->handleResourcesTemplatesList($request),
            'resources/read' => $this->handleResourcesRead($request),
            'prompts/list' => $this->handlePromptsList($request),
            'prompts/get' => $this->handlePromptsGet($request),
            default => JsonRpcResponse::error(
                $request->id,
                JsonRpcException::METHOD_NOT_FOUND,
                'Method not found: ' . $request->method,
            ),
        };
    }

    private function handleNotification(JsonRpcRequest $request): void
    {
        match ($request->method) {
            'notifications/initialized' => $this->handleInitializedNotification(),
            '$/cancelRequest' => $this->handleCancelRequest($request),
            'exit' => $this->handleExit(),
            default => null, // Ignore unknown notifications
        };
    }

    private function handleInitialize(JsonRpcRequest $request): JsonRpcResponse
    {
        $this->state = SessionState::Initializing;
        $result = $this->negotiator->initialize($request->params);

        return JsonRpcResponse::success($request->id, $result);
    }

    private function handleInitializedNotification(): void
    {
        if ($this->state === SessionState::Initializing) {
            $this->state = SessionState::Ready;
        }
    }

    private function handleShutdown(JsonRpcRequest $request): JsonRpcResponse
    {
        $this->state = SessionState::ShuttingDown;

        return JsonRpcResponse::success($request->id, []);
    }

    private function handleExit(): void
    {
        $this->state = SessionState::Closed;
    }

    private function handleCancelRequest(JsonRpcRequest $request): void
    {
        $requestId = $request->params['id'] ?? null;

        if ($requestId !== null) {
            $this->cancelledRequests[(string) $requestId] = true;
        }
    }

    private function handleToolsList(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::success($request->id, [
            'tools' => $this->toolRegistry->toList(),
        ]);
    }

    private function handleToolsCall(JsonRpcRequest $request): JsonRpcResponse
    {
        $toolName = $request->params['name'] ?? null;
        $arguments = $request->params['arguments'] ?? [];

        if (!is_string($toolName)) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_PARAMS,
                'Invalid params: name must be a string',
            );
        }

        $definition = $this->toolRegistry->get($toolName);

        if ($definition === null) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_PARAMS,
                'Unknown tool: ' . $toolName,
            );
        }

        if ($this->toolExecutor !== null) {
            try {
                $result = $this->toolExecutor->execute($definition, is_array($arguments) ? $arguments : []);

                return JsonRpcResponse::success($request->id, $result);
            } catch (\Throwable $e) {
                return JsonRpcResponse::success($request->id, [
                    'content' => [
                        ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
                    ],
                    'isError' => true,
                ]);
            }
        }

        // Without an executor, use reflection to invoke
        return $this->executeToolViaReflection($request->id, $definition, is_array($arguments) ? $arguments : []);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function executeToolViaReflection(
        string|int|null $id,
        \Lattice\Mcp\Registry\ToolDefinition $definition,
        array $arguments,
    ): JsonRpcResponse {
        try {
            $class = new \ReflectionClass($definition->className);
            $instance = $class->newInstanceWithoutConstructor();
            $method = $class->getMethod($definition->methodName);

            // Map arguments to method parameters
            $params = [];

            foreach ($method->getParameters() as $param) {
                $name = $param->getName();

                if (array_key_exists($name, $arguments)) {
                    $params[] = $arguments[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                } else {
                    return JsonRpcResponse::error(
                        $id,
                        JsonRpcException::INVALID_PARAMS,
                        'Missing required parameter: ' . $name,
                    );
                }
            }

            $result = $method->invokeArgs($instance, $params);

            $text = is_string($result) ? $result : (string) json_encode($result, JSON_PRETTY_PRINT);

            return JsonRpcResponse::success($id, [
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
            ]);
        } catch (\Throwable $e) {
            return JsonRpcResponse::success($id, [
                'content' => [
                    ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
                ],
                'isError' => true,
            ]);
        }
    }

    private function handleResourcesList(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::success($request->id, [
            'resources' => $this->resourceRegistry->toList(),
        ]);
    }

    private function handleResourcesTemplatesList(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::success($request->id, [
            'resourceTemplates' => $this->resourceRegistry->templateList(),
        ]);
    }

    private function handleResourcesRead(JsonRpcRequest $request): JsonRpcResponse
    {
        $uri = $request->params['uri'] ?? null;

        if (!is_string($uri)) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_PARAMS,
                'Invalid params: uri must be a string',
            );
        }

        $match = $this->resourceRegistry->match($uri);

        if ($match === null) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_PARAMS,
                'Unknown resource: ' . $uri,
            );
        }

        if ($this->resourceReader !== null) {
            try {
                $result = $this->resourceReader->read($match['definition'], $match['variables']);

                return JsonRpcResponse::success($request->id, $result);
            } catch (\Throwable $e) {
                return JsonRpcResponse::error(
                    $request->id,
                    JsonRpcException::INTERNAL_ERROR,
                    'Internal error: ' . $e->getMessage(),
                );
            }
        }

        // Fallback: invoke via reflection
        return $this->readResourceViaReflection($request->id, $match['definition'], $match['variables'], $uri);
    }

    /**
     * @param array<string, string> $variables
     */
    private function readResourceViaReflection(
        string|int|null $id,
        \Lattice\Mcp\Registry\ResourceDefinition $definition,
        array $variables,
        string $uri,
    ): JsonRpcResponse {
        try {
            $class = new \ReflectionClass($definition->className);
            $instance = $class->newInstanceWithoutConstructor();
            $method = $class->getMethod($definition->methodName);

            $params = [];

            foreach ($method->getParameters() as $param) {
                $name = $param->getName();

                if (isset($variables[$name])) {
                    $value = $variables[$name];

                    // Type cast if needed
                    $type = $param->getType();

                    if ($type instanceof \ReflectionNamedType) {
                        $value = match ($type->getName()) {
                            'int' => (int) $value,
                            'float' => (float) $value,
                            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                            default => $value,
                        };
                    }

                    $params[] = $value;
                } elseif ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                }
            }

            $result = $method->invokeArgs($instance, $params);
            $text = is_string($result) ? $result : (string) json_encode($result, JSON_PRETTY_PRINT);

            return JsonRpcResponse::success($id, [
                'contents' => [
                    [
                        'uri' => $uri,
                        'mimeType' => $definition->mimeType,
                        'text' => $text,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return JsonRpcResponse::error(
                $id,
                JsonRpcException::INTERNAL_ERROR,
                'Internal error: ' . $e->getMessage(),
            );
        }
    }

    private function handlePromptsList(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::success($request->id, [
            'prompts' => $this->promptRegistry->toList(),
        ]);
    }

    private function handlePromptsGet(JsonRpcRequest $request): JsonRpcResponse
    {
        $name = $request->params['name'] ?? null;
        $arguments = $request->params['arguments'] ?? [];

        if (!is_string($name)) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_PARAMS,
                'Invalid params: name must be a string',
            );
        }

        $definition = $this->promptRegistry->get($name);

        if ($definition === null) {
            return JsonRpcResponse::error(
                $request->id,
                JsonRpcException::INVALID_PARAMS,
                'Unknown prompt: ' . $name,
            );
        }

        // Validate required arguments
        foreach ($definition->arguments as $argDef) {
            if ($argDef->required && !isset($arguments[$argDef->name])) {
                return JsonRpcResponse::error(
                    $request->id,
                    JsonRpcException::INVALID_PARAMS,
                    'Missing required argument: ' . $argDef->name,
                );
            }
        }

        if ($this->promptRenderer !== null) {
            try {
                $result = $this->promptRenderer->render($definition, is_array($arguments) ? $arguments : []);

                return JsonRpcResponse::success($request->id, $result);
            } catch (\Throwable $e) {
                return JsonRpcResponse::error(
                    $request->id,
                    JsonRpcException::INTERNAL_ERROR,
                    'Internal error: ' . $e->getMessage(),
                );
            }
        }

        // Fallback: invoke via reflection
        return $this->renderPromptViaReflection($request->id, $definition, is_array($arguments) ? $arguments : []);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function renderPromptViaReflection(
        string|int|null $id,
        \Lattice\Mcp\Registry\PromptDefinition $definition,
        array $arguments,
    ): JsonRpcResponse {
        try {
            $class = new \ReflectionClass($definition->className);
            $instance = $class->newInstanceWithoutConstructor();
            $method = $class->getMethod($definition->methodName);

            $params = [];

            foreach ($method->getParameters() as $param) {
                $name = $param->getName();

                if (array_key_exists($name, $arguments)) {
                    $params[] = $arguments[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                }
            }

            $result = $method->invokeArgs($instance, $params);

            // Expect the method to return an array of messages
            if (!is_array($result)) {
                $result = [
                    ['role' => 'user', 'content' => ['type' => 'text', 'text' => (string) $result]],
                ];
            }

            return JsonRpcResponse::success($id, [
                'description' => $definition->description,
                'messages' => $result,
            ]);
        } catch (\Throwable $e) {
            return JsonRpcResponse::error(
                $id,
                JsonRpcException::INTERNAL_ERROR,
                'Internal error: ' . $e->getMessage(),
            );
        }
    }
}

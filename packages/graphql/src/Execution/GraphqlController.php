<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Execution;

use Lattice\Http\Request;
use Lattice\Http\Response;

final class GraphqlController
{
    public function __construct(
        private readonly QueryExecutor $executor,
    ) {}

    /**
     * Handle POST /graphql endpoint.
     */
    public function handle(Request $request): Response
    {
        $body = $request->getBody();

        if (!is_array($body)) {
            return Response::json(
                ['errors' => [['message' => 'Request body must be a JSON object with a "query" field']]],
                400,
            );
        }

        // Check for batched queries (array of operations)
        if ($this->isBatchedRequest($body)) {
            return $this->handleBatch($body);
        }

        return $this->handleSingle($body);
    }

    /**
     * Handle a single GraphQL operation.
     *
     * @param array<string, mixed> $body
     */
    private function handleSingle(array $body): Response
    {
        if (!isset($body['query']) || !is_string($body['query'])) {
            return Response::json(
                ['errors' => [['message' => 'Missing required "query" field']]],
                400,
            );
        }

        $graphqlRequest = GraphqlRequest::fromArray($body);
        $graphqlResponse = $this->executor->execute($graphqlRequest);

        return Response::json($graphqlResponse->toArray());
    }

    /**
     * Handle a batched request (array of operations).
     *
     * @param array<array<string, mixed>> $batch
     */
    private function handleBatch(array $batch): Response
    {
        $results = [];

        foreach ($batch as $operation) {
            if (!is_array($operation) || !isset($operation['query'])) {
                $results[] = ['errors' => [['message' => 'Each batched operation must have a "query" field']]];
                continue;
            }

            $graphqlRequest = GraphqlRequest::fromArray($operation);
            $graphqlResponse = $this->executor->execute($graphqlRequest);
            $results[] = $graphqlResponse->toArray();
        }

        return Response::json($results);
    }

    /**
     * Determine if this is a batched request (numerically indexed array).
     *
     * @param array<mixed> $body
     */
    private function isBatchedRequest(array $body): bool
    {
        if (empty($body)) {
            return false;
        }

        return array_is_list($body);
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Http;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lattice\Http\Exception\HttpException;
use Lattice\Validation\Exceptions\ValidationException;

final class ExceptionHandler
{
    public function handle(\Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return $this->handleValidationException($e);
        }

        if ($e instanceof HttpException) {
            return Response::error($e->getMessage(), $e->getStatusCode());
        }

        // Eloquent model not found → 404
        if (class_exists(ModelNotFoundException::class) && $e instanceof ModelNotFoundException) {
            return Response::error('Not Found', 404);
        }

        return Response::error('Internal Server Error', 500);
    }

    /**
     * Format a ValidationException as RFC 9457 Problem Details.
     */
    private function handleValidationException(ValidationException $e): Response
    {
        $errors = [];

        foreach ($e->getValidationResult()->getErrors() as $fieldError) {
            $errors[$fieldError->field][] = $fieldError->message;
        }

        $body = [
            'type' => 'https://httpstatuses.io/422',
            'title' => 'Validation Failed',
            'status' => 422,
            'detail' => 'The given data was invalid.',
            'errors' => $errors,
        ];

        return new Response(
            statusCode: 422,
            headers: [
                'Content-Type' => 'application/problem+json',
            ],
            body: $body,
        );
    }
}

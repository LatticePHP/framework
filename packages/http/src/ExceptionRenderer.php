<?php

declare(strict_types=1);

namespace Lattice\Http;

use Lattice\Http\Exception\HttpException;
use Lattice\Validation\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;

final class ExceptionRenderer
{
    private const STATUS_TEXTS = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    public function __construct(
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function render(\Throwable $e): Response
    {
        $this->logException($e);

        $status = $this->getStatusCode($e);

        $body = [
            'type' => "https://httpstatuses.io/{$status}",
            'title' => $this->getTitle($status),
            'status' => $status,
            'detail' => $this->getDetail($e),
            'instance' => uniqid('err-'),
        ];

        if ($e instanceof ValidationException) {
            $errors = [];
            foreach ($e->getValidationResult()->getErrors() as $fieldError) {
                $errors[$fieldError->field][] = $fieldError->message;
            }
            $body['errors'] = $errors;
        }

        if ($this->debug) {
            $body['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 10),
            ];
        }

        return new Response(
            statusCode: $status,
            headers: ['Content-Type' => 'application/problem+json'],
            body: $body,
        );
    }

    private function getStatusCode(\Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }

        return 500;
    }

    private function getTitle(int $code): string
    {
        return self::STATUS_TEXTS[$code] ?? 'Error';
    }

    private function getDetail(\Throwable $e): string
    {
        if ($e instanceof HttpException) {
            return $e->getMessage();
        }

        if ($e instanceof ValidationException) {
            return 'The given data was invalid.';
        }

        if ($e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        // Never expose internal exception messages in production
        return 'An unexpected error occurred.';
    }

    private function logException(\Throwable $e): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if ($this->getStatusCode($e) >= 500) {
            $this->logger->error($e->getMessage(), $context);
        } else {
            $this->logger->warning($e->getMessage(), $context);
        }
    }
}

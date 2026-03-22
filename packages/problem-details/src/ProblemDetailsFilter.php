<?php

declare(strict_types=1);

namespace Lattice\ProblemDetails;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;

final class ProblemDetailsFilter implements ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
    {
        if ($exception instanceof ProblemDetailsException) {
            $problem = $exception->problemDetails;
        } elseif (class_exists(\Lattice\Validation\Exceptions\ValidationException::class)
            && $exception instanceof \Lattice\Validation\Exceptions\ValidationException) {
            $errors = [];
            foreach ($exception->getValidationResult()->getErrors() as $fieldError) {
                $errors[$fieldError->field][] = $fieldError->message;
            }
            $problem = new ProblemDetails(
                status: 422,
                title: 'Validation Failed',
                detail: 'The given data was invalid.',
                extensions: ['errors' => $errors],
            );
        } elseif (class_exists(\Illuminate\Database\Eloquent\ModelNotFoundException::class)
            && $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $problem = new ProblemDetails(
                status: 404,
                title: 'Not Found',
                detail: 'The requested resource was not found.',
            );
        } elseif ($exception instanceof \Lattice\Http\Exception\HttpException) {
            $problem = new ProblemDetails(
                status: $exception->getStatusCode(),
                title: 'Error',
                detail: $exception->getMessage(),
            );
        } else {
            $problem = ProblemDetails::internalError();
        }

        $correlationId = $context->getCorrelationId();

        $withCorrelation = new ProblemDetails(
            status: $problem->status,
            title: $problem->title,
            type: $problem->type,
            detail: $problem->detail,
            instance: $problem->instance,
            correlationId: $correlationId,
            extensions: $problem->extensions,
        );

        // Return a Response with the correct status code so the kernel doesn't wrap it in 200
        return new \Lattice\Http\Response(
            statusCode: $withCorrelation->status,
            headers: ['Content-Type' => 'application/problem+json'],
            body: $withCorrelation->toArray(),
        );
    }
}

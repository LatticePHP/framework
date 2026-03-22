<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;
use Throwable;

final class ExceptionWatcher extends AbstractWatcher
{
    /** @var list<class-string<Throwable>> */
    private readonly array $ignoredExceptions;

    /**
     * @param list<class-string<Throwable>> $ignoredExceptions
     */
    public function __construct(
        StorageManager $storage,
        array $ignoredExceptions = [],
    ) {
        parent::__construct($storage);
        $this->ignoredExceptions = $ignoredExceptions;
    }

    /**
     * Capture an exception.
     *
     * @param array<string, mixed> $requestContext
     */
    public function capture(
        Throwable $exception,
        array $requestContext = [],
        ?string $batchId = null,
    ): ?Entry {
        if ($this->isIgnored($exception)) {
            return null;
        }

        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception),
            'request_context' => $requestContext,
        ];

        if ($exception->getPrevious() !== null) {
            $data['previous'] = $this->formatPrevious($exception->getPrevious());
        }

        if ($exception instanceof ContextProviderInterface) {
            $data['custom_context'] = $exception->context();
        }

        $entry = new Entry(
            type: EntryType::Exception,
            data: $data,
            tags: ['exception:' . $this->shortClassName(get_class($exception))],
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    public function shouldRecord(Entry $entry): bool
    {
        $class = $entry->data['class'] ?? '';

        foreach ($this->ignoredExceptions as $ignored) {
            if ($class === $ignored || is_subclass_of($class, $ignored)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatTrace(Throwable $exception): array
    {
        $trace = [];

        foreach ($exception->getTrace() as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
        }

        return $trace;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPrevious(Throwable $previous): array
    {
        $data = [
            'class' => get_class($previous),
            'message' => $previous->getMessage(),
            'code' => $previous->getCode(),
            'file' => $previous->getFile(),
            'line' => $previous->getLine(),
        ];

        if ($previous->getPrevious() !== null) {
            $data['previous'] = $this->formatPrevious($previous->getPrevious());
        }

        return $data;
    }

    private function shortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    private function isIgnored(Throwable $exception): bool
    {
        foreach ($this->ignoredExceptions as $ignored) {
            if ($exception instanceof $ignored) {
                return true;
            }
        }

        return false;
    }
}

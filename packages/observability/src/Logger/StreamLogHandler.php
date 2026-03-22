<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

final class StreamLogHandler implements LogHandlerInterface
{
    /** @var resource */
    private $stream;
    private readonly bool $closeOnDestruct;

    /**
     * @param resource|string $stream A stream resource or file path
     */
    public function __construct(mixed $stream)
    {
        if (is_string($stream)) {
            $resource = fopen($stream, 'a');
            if ($resource === false) {
                throw new \RuntimeException(sprintf('Unable to open stream: %s', $stream));
            }
            $this->stream = $resource;
            $this->closeOnDestruct = true;
        } elseif (is_resource($stream)) {
            $this->stream = $stream;
            $this->closeOnDestruct = false;
        } else {
            throw new \InvalidArgumentException('Stream must be a resource or a file path string.');
        }
    }

    public function handle(LogEntry $entry): void
    {
        $json = json_encode($entry->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        fwrite($this->stream, $json . "\n");
    }

    public function __destruct()
    {
        if ($this->closeOnDestruct && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Prism\Event;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class ErrorEvent
{
    /**
     * @param list<StackFrame> $stacktrace
     * @param array<string, mixed> $context
     * @param array<string, string> $tags
     * @param list<string>|null $fingerprint Custom fingerprint override
     */
    public function __construct(
        public readonly string $eventId,
        public readonly DateTimeImmutable $timestamp,
        public readonly string $projectId,
        public readonly string $environment,
        public readonly string $platform,
        public readonly ErrorLevel $level,
        public readonly ?string $exceptionType,
        public readonly ?string $exceptionMessage,
        public readonly array $stacktrace = [],
        public readonly array $context = [],
        public readonly array $tags = [],
        public readonly ?string $release = null,
        public readonly ?string $serverName = null,
        public readonly ?string $transaction = null,
        public readonly ?array $fingerprint = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        self::validate($data);

        $eventId = isset($data['event_id']) && is_string($data['event_id']) && $data['event_id'] !== ''
            ? $data['event_id']
            : self::generateUuidV4();

        $timestamp = isset($data['timestamp']) && is_string($data['timestamp'])
            ? new DateTimeImmutable($data['timestamp'])
            : new DateTimeImmutable();

        $level = ErrorLevel::from((string) $data['level']);

        $stacktrace = [];
        $exceptionType = null;
        $exceptionMessage = null;

        if (isset($data['exception']) && is_array($data['exception'])) {
            $exceptionType = isset($data['exception']['type']) ? (string) $data['exception']['type'] : null;
            $exceptionMessage = isset($data['exception']['value']) ? (string) $data['exception']['value'] : (
                isset($data['exception']['message']) ? (string) $data['exception']['message'] : null
            );

            if (isset($data['exception']['stacktrace']) && is_array($data['exception']['stacktrace'])) {
                foreach ($data['exception']['stacktrace'] as $frame) {
                    if (is_array($frame)) {
                        $stacktrace[] = StackFrame::fromArray($frame);
                    }
                }
            }
        }

        $tags = [];
        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $key => $value) {
                if (is_string($key) && (is_string($value) || is_numeric($value))) {
                    $tags[$key] = (string) $value;
                }
            }
        }

        $context = [];
        if (isset($data['context']) && is_array($data['context'])) {
            $context = $data['context'];
        }

        /** @var list<string>|null $fingerprint */
        $fingerprint = null;
        if (isset($data['fingerprint']) && is_array($data['fingerprint'])) {
            $fingerprint = array_values(array_map('strval', $data['fingerprint']));
        }

        return new self(
            eventId: $eventId,
            timestamp: $timestamp,
            projectId: (string) $data['project_id'],
            environment: (string) $data['environment'],
            platform: (string) $data['platform'],
            level: $level,
            exceptionType: $exceptionType,
            exceptionMessage: $exceptionMessage,
            stacktrace: $stacktrace,
            context: $context,
            tags: $tags,
            release: isset($data['release']) ? (string) $data['release'] : null,
            serverName: isset($data['server_name']) ? (string) $data['server_name'] : null,
            transaction: isset($data['transaction']) ? (string) $data['transaction'] : null,
            fingerprint: $fingerprint,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'event_id' => $this->eventId,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'project_id' => $this->projectId,
            'environment' => $this->environment,
            'platform' => $this->platform,
            'level' => $this->level->value,
        ];

        $exception = [];
        if ($this->exceptionType !== null) {
            $exception['type'] = $this->exceptionType;
        }
        if ($this->exceptionMessage !== null) {
            $exception['value'] = $this->exceptionMessage;
        }
        if ($this->stacktrace !== []) {
            $exception['stacktrace'] = array_map(
                static fn(StackFrame $frame): array => $frame->toArray(),
                $this->stacktrace,
            );
        }
        if ($exception !== []) {
            $data['exception'] = $exception;
        }

        if ($this->context !== []) {
            $data['context'] = $this->context;
        }

        if ($this->tags !== []) {
            $data['tags'] = $this->tags;
        }

        if ($this->release !== null) {
            $data['release'] = $this->release;
        }

        if ($this->serverName !== null) {
            $data['server_name'] = $this->serverName;
        }

        if ($this->transaction !== null) {
            $data['transaction'] = $this->transaction;
        }

        if ($this->fingerprint !== null) {
            $data['fingerprint'] = $this->fingerprint;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public static function validateAndGetErrors(array $data): array
    {
        $errors = [];

        if (!isset($data['project_id']) || !is_string($data['project_id']) || $data['project_id'] === '') {
            $errors[] = 'project_id is required and must be a non-empty string';
        }

        if (!isset($data['environment']) || !is_string($data['environment']) || $data['environment'] === '') {
            $errors[] = 'environment is required and must be a non-empty string';
        }

        if (!isset($data['platform']) || !is_string($data['platform']) || $data['platform'] === '') {
            $errors[] = 'platform is required and must be a non-empty string';
        }

        if (!isset($data['level']) || !is_string($data['level'])) {
            $errors[] = 'level is required and must be a string';
        } elseif (!ErrorLevel::isValid($data['level'])) {
            $errors[] = sprintf('level must be one of: error, warning, fatal, info (got: %s)', $data['level']);
        }

        if (isset($data['event_id']) && is_string($data['event_id']) && $data['event_id'] !== '') {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $data['event_id'])) {
                $errors[] = 'event_id must be a valid UUID v4';
            }
        }

        if (isset($data['timestamp']) && is_string($data['timestamp'])) {
            $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['timestamp']);
            if ($parsed === false) {
                // Try more lenient parsing
                try {
                    new DateTimeImmutable($data['timestamp']);
                } catch (\Exception) {
                    $errors[] = 'timestamp must be a valid ISO 8601 date';
                }
            }
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $tagCount = 0;
            foreach ($data['tags'] as $key => $value) {
                $tagCount++;
                if (is_string($key) && strlen($key) > 32) {
                    $errors[] = sprintf('tag key "%s" exceeds max length of 32 characters', $key);
                }
                if ((is_string($value) || is_numeric($value)) && strlen((string) $value) > 200) {
                    $errors[] = sprintf('tag value for key "%s" exceeds max length of 200 characters', (string) $key);
                }
            }
            if ($tagCount > 50) {
                $errors[] = 'maximum 50 tags allowed';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function validate(array $data): void
    {
        $errors = self::validateAndGetErrors($data);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode('; ', $errors));
        }
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );
    }
}

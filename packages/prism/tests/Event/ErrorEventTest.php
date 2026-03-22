<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Event;

use DateTimeImmutable;
use InvalidArgumentException;
use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\StackFrame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorEventTest extends TestCase
{
    #[Test]
    public function test_from_array_constructs_valid_event(): void
    {
        $data = $this->validEventData();
        $event = ErrorEvent::fromArray($data);

        $this->assertSame('proj-1', $event->projectId);
        $this->assertSame('production', $event->environment);
        $this->assertSame('php', $event->platform);
        $this->assertSame(ErrorLevel::Error, $event->level);
        $this->assertSame('RuntimeException', $event->exceptionType);
        $this->assertSame('Something went wrong', $event->exceptionMessage);
        $this->assertCount(2, $event->stacktrace);
        $this->assertSame('v1.2.3', $event->release);
    }

    #[Test]
    public function test_to_array_round_trip(): void
    {
        $data = $this->validEventData();
        $event = ErrorEvent::fromArray($data);
        $exported = $event->toArray();

        $this->assertSame($event->eventId, $exported['event_id']);
        $this->assertSame('proj-1', $exported['project_id']);
        $this->assertSame('production', $exported['environment']);
        $this->assertSame('php', $exported['platform']);
        $this->assertSame('error', $exported['level']);
        $this->assertSame('RuntimeException', $exported['exception']['type']);
        $this->assertSame('Something went wrong', $exported['exception']['value']);
        $this->assertCount(2, $exported['exception']['stacktrace']);
        $this->assertSame('v1.2.3', $exported['release']);
    }

    #[Test]
    public function test_round_trip_reconstruct(): void
    {
        $data = $this->validEventData();
        $event = ErrorEvent::fromArray($data);
        $exported = $event->toArray();

        // Re-construct from exported data
        $reconstructed = ErrorEvent::fromArray($exported);

        $this->assertSame($event->eventId, $reconstructed->eventId);
        $this->assertSame($event->projectId, $reconstructed->projectId);
        $this->assertSame($event->environment, $reconstructed->environment);
        $this->assertSame($event->platform, $reconstructed->platform);
        $this->assertSame($event->level, $reconstructed->level);
        $this->assertSame($event->exceptionType, $reconstructed->exceptionType);
        $this->assertSame($event->exceptionMessage, $reconstructed->exceptionMessage);
        $this->assertCount(count($event->stacktrace), $reconstructed->stacktrace);
    }

    #[Test]
    public function test_generates_event_id_when_missing(): void
    {
        $data = $this->validEventData();
        unset($data['event_id']);

        $event = ErrorEvent::fromArray($data);

        $this->assertNotEmpty($event->eventId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $event->eventId,
        );
    }

    #[Test]
    public function test_generates_timestamp_when_missing(): void
    {
        $data = $this->validEventData();
        unset($data['timestamp']);

        $event = ErrorEvent::fromArray($data);

        $this->assertInstanceOf(DateTimeImmutable::class, $event->timestamp);
    }

    #[Test]
    public function test_validation_rejects_missing_project_id(): void
    {
        $data = $this->validEventData();
        unset($data['project_id']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('project_id is required');
        ErrorEvent::fromArray($data);
    }

    #[Test]
    public function test_validation_rejects_missing_environment(): void
    {
        $data = $this->validEventData();
        unset($data['environment']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('environment is required');
        ErrorEvent::fromArray($data);
    }

    #[Test]
    public function test_validation_rejects_missing_platform(): void
    {
        $data = $this->validEventData();
        unset($data['platform']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('platform is required');
        ErrorEvent::fromArray($data);
    }

    #[Test]
    public function test_validation_rejects_invalid_level(): void
    {
        $data = $this->validEventData();
        $data['level'] = 'critical';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('level must be one of');
        ErrorEvent::fromArray($data);
    }

    #[Test]
    public function test_validation_rejects_invalid_uuid(): void
    {
        $data = $this->validEventData();
        $data['event_id'] = 'not-a-valid-uuid';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event_id must be a valid UUID v4');
        ErrorEvent::fromArray($data);
    }

    #[Test]
    public function test_tag_validation_max_key_length(): void
    {
        $data = $this->validEventData();
        $data['tags'] = [str_repeat('a', 33) => 'value'];

        $errors = ErrorEvent::validateAndGetErrors($data);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('exceeds max length of 32', $errors[0]);
    }

    #[Test]
    public function test_tag_validation_max_value_length(): void
    {
        $data = $this->validEventData();
        $data['tags'] = ['key' => str_repeat('v', 201)];

        $errors = ErrorEvent::validateAndGetErrors($data);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('exceeds max length of 200', $errors[0]);
    }

    #[Test]
    public function test_tag_validation_max_count(): void
    {
        $data = $this->validEventData();
        $tags = [];
        for ($i = 0; $i < 51; $i++) {
            $tags["key$i"] = "val$i";
        }
        $data['tags'] = $tags;

        $errors = ErrorEvent::validateAndGetErrors($data);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('maximum 50 tags', $errors[0]);
    }

    #[Test]
    public function test_context_round_trip(): void
    {
        $data = $this->validEventData();
        $data['context'] = [
            'request' => ['url' => '/api/v1/users', 'method' => 'GET'],
            'user' => ['id' => '42', 'email' => 'test@example.com'],
        ];

        $event = ErrorEvent::fromArray($data);
        $exported = $event->toArray();

        $this->assertSame('/api/v1/users', $exported['context']['request']['url']);
        $this->assertSame('42', $exported['context']['user']['id']);
    }

    #[Test]
    public function test_custom_fingerprint_preserved(): void
    {
        $data = $this->validEventData();
        $data['fingerprint'] = ['custom', 'grouping', 'key'];

        $event = ErrorEvent::fromArray($data);

        $this->assertSame(['custom', 'grouping', 'key'], $event->fingerprint);

        $exported = $event->toArray();
        $this->assertSame(['custom', 'grouping', 'key'], $exported['fingerprint']);
    }

    #[Test]
    public function test_stackframe_from_array_and_to_array(): void
    {
        $frame = StackFrame::fromArray([
            'file' => '/app/src/Service.php',
            'line' => 42,
            'function' => 'handle',
            'class' => 'App\\Service',
            'module' => 'app',
            'column' => 10,
            'code_context' => [
                'pre' => ['line1', 'line2'],
                'line' => 'the_line',
                'post' => ['line4', 'line5'],
            ],
        ]);

        $this->assertSame('/app/src/Service.php', $frame->file);
        $this->assertSame(42, $frame->line);
        $this->assertSame('handle', $frame->function);
        $this->assertSame('App\\Service', $frame->class);
        $this->assertSame(10, $frame->column);

        $arr = $frame->toArray();
        $this->assertSame('/app/src/Service.php', $arr['file']);
        $this->assertSame(42, $arr['line']);
        $this->assertArrayHasKey('code_context', $arr);
    }

    #[Test]
    public function test_all_error_levels(): void
    {
        foreach (['error', 'warning', 'fatal', 'info'] as $level) {
            $data = $this->validEventData();
            $data['level'] = $level;

            $event = ErrorEvent::fromArray($data);
            $this->assertSame($level, $event->level->value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validEventData(): array
    {
        return [
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'timestamp' => '2026-03-22T10:00:00+00:00',
            'project_id' => 'proj-1',
            'environment' => 'production',
            'platform' => 'php',
            'level' => 'error',
            'release' => 'v1.2.3',
            'server_name' => 'web-01',
            'transaction' => 'GET /api/users',
            'exception' => [
                'type' => 'RuntimeException',
                'value' => 'Something went wrong',
                'stacktrace' => [
                    [
                        'file' => '/app/src/Controller/UserController.php',
                        'line' => 42,
                        'function' => 'index',
                        'class' => 'App\\Controller\\UserController',
                    ],
                    [
                        'file' => '/app/src/Service/UserService.php',
                        'line' => 88,
                        'function' => 'findAll',
                        'class' => 'App\\Service\\UserService',
                    ],
                ],
            ],
            'tags' => [
                'browser' => 'Chrome',
                'os' => 'Linux',
            ],
            'context' => [
                'request' => ['url' => '/api/users', 'method' => 'GET'],
            ],
        ];
    }
}

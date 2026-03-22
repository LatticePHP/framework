<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Fingerprint;

use DateTimeImmutable;
use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\StackFrame;
use Lattice\Prism\Fingerprint\Fingerprinter;
use Lattice\Prism\Fingerprint\StacktraceNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FingerprinterTest extends TestCase
{
    private Fingerprinter $fingerprinter;

    protected function setUp(): void
    {
        $this->fingerprinter = new Fingerprinter();
    }

    #[Test]
    public function test_same_error_different_line_numbers_same_fingerprint(): void
    {
        $event1 = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
                new StackFrame(file: '/app/src/Controller.php', line: 100, function: 'index', class: 'App\\Controller'),
            ],
        );

        $event2 = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 55, function: 'handle', class: 'App\\Service'),
                new StackFrame(file: '/app/src/Controller.php', line: 120, function: 'index', class: 'App\\Controller'),
            ],
        );

        $fp1 = $this->fingerprinter->generate($event1);
        $fp2 = $this->fingerprinter->generate($event2);

        $this->assertSame($fp1, $fp2, 'Same error with different line numbers should produce the same fingerprint');
        $this->assertSame(64, strlen($fp1), 'Fingerprint should be a 64-character hex SHA-256 hash');
    }

    #[Test]
    public function test_different_exception_types_different_fingerprint(): void
    {
        $event1 = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
            ],
        );

        $event2 = $this->makeEvent(
            exceptionType: 'InvalidArgumentException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
            ],
        );

        $fp1 = $this->fingerprinter->generate($event1);
        $fp2 = $this->fingerprinter->generate($event2);

        $this->assertNotSame($fp1, $fp2, 'Different exception types should produce different fingerprints');
    }

    #[Test]
    public function test_vendor_frames_excluded(): void
    {
        $event1 = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
                new StackFrame(file: '/app/vendor/laravel/framework/src/Something.php', line: 10, function: 'run', class: 'Laravel\\Something'),
            ],
        );

        $event2 = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
                new StackFrame(file: '/app/vendor/laravel/framework/src/Other.php', line: 20, function: 'execute', class: 'Laravel\\Other'),
            ],
        );

        $fp1 = $this->fingerprinter->generate($event1);
        $fp2 = $this->fingerprinter->generate($event2);

        $this->assertSame($fp1, $fp2, 'Vendor frames should be excluded from fingerprinting');
    }

    #[Test]
    public function test_custom_fingerprint_override(): void
    {
        $event = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
            ],
            fingerprint: ['custom', 'group', 'key'],
        );

        $fp = $this->fingerprinter->generate($event);

        // The custom fingerprint should be deterministic
        $expected = hash('sha256', 'custom|group|key');
        $this->assertSame($expected, $fp);
    }

    #[Test]
    public function test_no_stacktrace_uses_type_and_message(): void
    {
        $event = $this->makeEvent(
            exceptionType: 'RuntimeException',
            exceptionMessage: 'Connection refused',
            stacktrace: [],
        );

        $fp = $this->fingerprinter->generate($event);

        $expected = hash('sha256', 'RuntimeException|Connection refused');
        $this->assertSame($expected, $fp);
    }

    #[Test]
    public function test_no_exception_uses_level_and_transaction(): void
    {
        $event = new ErrorEvent(
            eventId: '550e8400-e29b-41d4-a716-446655440000',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Warning,
            exceptionType: null,
            exceptionMessage: 'High memory usage',
            stacktrace: [],
            transaction: 'cron:cleanup',
        );

        $fp = $this->fingerprinter->generate($event);

        $expected = hash('sha256', 'warning|cron:cleanup|High memory usage');
        $this->assertSame($expected, $fp);
    }

    #[Test]
    public function test_fingerprint_is_consistent_across_calls(): void
    {
        $event = $this->makeEvent(
            exceptionType: 'RuntimeException',
            stacktrace: [
                new StackFrame(file: '/app/src/Service.php', line: 42, function: 'handle', class: 'App\\Service'),
            ],
        );

        $fp1 = $this->fingerprinter->generate($event);
        $fp2 = $this->fingerprinter->generate($event);
        $fp3 = $this->fingerprinter->generate($event);

        $this->assertSame($fp1, $fp2);
        $this->assertSame($fp2, $fp3);
    }

    #[Test]
    public function test_node_modules_excluded(): void
    {
        $event = $this->makeEvent(
            exceptionType: 'TypeError',
            stacktrace: [
                new StackFrame(file: 'src/components/App.tsx', line: 10, function: 'render', class: 'App'),
                new StackFrame(file: 'node_modules/react/index.js', line: 5, function: 'createElement', class: 'React'),
            ],
        );

        $fp = $this->fingerprinter->generate($event);

        // Only app frame should be included
        $event2 = $this->makeEvent(
            exceptionType: 'TypeError',
            stacktrace: [
                new StackFrame(file: 'src/components/App.tsx', line: 10, function: 'render', class: 'App'),
                new StackFrame(file: 'node_modules/react-dom/index.js', line: 99, function: 'hydrate', class: 'ReactDOM'),
            ],
        );

        $fp2 = $this->fingerprinter->generate($event2);

        $this->assertSame($fp, $fp2, 'node_modules frames should be excluded');
    }

    #[Test]
    public function test_stacktrace_normalizer_strip_paths(): void
    {
        $normalizer = new StacktraceNormalizer();

        $this->assertSame('src/Service.php', $normalizer->normalizePath('/home/user/project/src/Service.php'));
        $this->assertSame('src/Service.php', $normalizer->normalizePath('C:/Users/dev/project/src/Service.php'));
        $this->assertSame('src/Service.php', $normalizer->normalizePath('/app/src/Service.php'));
        $this->assertSame('src/App.tsx', $normalizer->normalizePath('webpack:///src/App.tsx'));
    }

    /**
     * @param list<StackFrame> $stacktrace
     * @param list<string>|null $fingerprint
     */
    private function makeEvent(
        string $exceptionType = 'RuntimeException',
        string $exceptionMessage = 'Something went wrong',
        array $stacktrace = [],
        ?array $fingerprint = null,
    ): ErrorEvent {
        return new ErrorEvent(
            eventId: '550e8400-e29b-41d4-a716-446655440000',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: $exceptionType,
            exceptionMessage: $exceptionMessage,
            stacktrace: $stacktrace,
            fingerprint: $fingerprint,
        );
    }
}

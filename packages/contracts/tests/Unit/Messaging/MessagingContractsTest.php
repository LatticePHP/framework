<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Messaging;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MessagingContractsTest extends TestCase
{
    #[Test]
    public function messageEnvelopeInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(MessageEnvelopeInterface::class));
    }

    #[Test]
    public function messageEnvelopeInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(MessageEnvelopeInterface::class);

        $expectedMethods = [
            'getMessageId' => 'string',
            'getMessageType' => 'string',
            'getSchemaVersion' => 'string',
            'getCorrelationId' => 'string',
            'getPayload' => 'mixed',
            'getHeaders' => 'array',
            'getTimestamp' => \DateTimeImmutable::class,
            'getAttempt' => 'int',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }

        // getCausationId is nullable
        $this->assertTrue($reflection->hasMethod('getCausationId'));
        $causationReturn = $reflection->getMethod('getCausationId')->getReturnType();
        $this->assertTrue($causationReturn->allowsNull());
        $this->assertSame('string', $causationReturn->getName());
    }

    #[Test]
    public function messageEnvelopeCanBeImplemented(): void
    {
        $now = new \DateTimeImmutable();

        $envelope = new class ($now) implements MessageEnvelopeInterface {
            public function __construct(private \DateTimeImmutable $now) {}
            public function getMessageId(): string { return 'msg-1'; }
            public function getMessageType(): string { return 'user.created'; }
            public function getSchemaVersion(): string { return '1.0'; }
            public function getCorrelationId(): string { return 'corr-1'; }
            public function getCausationId(): ?string { return null; }
            public function getPayload(): mixed { return ['name' => 'John']; }
            public function getHeaders(): array { return ['x-source' => 'test']; }
            public function getTimestamp(): \DateTimeImmutable { return $this->now; }
            public function getAttempt(): int { return 1; }
        };

        $this->assertSame('msg-1', $envelope->getMessageId());
        $this->assertSame('user.created', $envelope->getMessageType());
        $this->assertSame('1.0', $envelope->getSchemaVersion());
        $this->assertSame('corr-1', $envelope->getCorrelationId());
        $this->assertNull($envelope->getCausationId());
        $this->assertSame(['name' => 'John'], $envelope->getPayload());
        $this->assertSame(['x-source' => 'test'], $envelope->getHeaders());
        $this->assertSame($now, $envelope->getTimestamp());
        $this->assertSame(1, $envelope->getAttempt());
    }

    #[Test]
    public function transportInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(TransportInterface::class));
    }

    #[Test]
    public function transportInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(TransportInterface::class);

        $expectedMethods = [
            'publish' => 'void',
            'subscribe' => 'void',
            'acknowledge' => 'void',
            'reject' => 'void',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }

        // Check publish parameters
        $publishParams = $reflection->getMethod('publish')->getParameters();
        $this->assertCount(2, $publishParams);
        $this->assertSame(MessageEnvelopeInterface::class, $publishParams[0]->getType()->getName());
        $this->assertSame('string', $publishParams[1]->getType()->getName());

        // Check reject has requeue parameter with default
        $rejectParams = $reflection->getMethod('reject')->getParameters();
        $this->assertCount(2, $rejectParams);
        $this->assertTrue($rejectParams[1]->isDefaultValueAvailable());
        $this->assertFalse($rejectParams[1]->getDefaultValue());
    }
}

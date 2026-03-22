<?php

declare(strict_types=1);

namespace Lattice\Transport\RabbitMq\Tests\Unit;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Transport\RabbitMq\DeadLetterHandler;
use Lattice\Transport\RabbitMq\Testing\FakeRabbitMqTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeadLetterHandlerTest extends TestCase
{
    private FakeRabbitMqTransport $transport;
    private DeadLetterHandler $handler;

    protected function setUp(): void
    {
        $this->transport = new FakeRabbitMqTransport();
        $this->handler = new DeadLetterHandler($this->transport);
    }

    #[Test]
    public function isNotConfiguredByDefault(): void
    {
        $this->assertFalse($this->handler->isConfigured());
        $this->assertNull($this->handler->getDlqExchange());
        $this->assertNull($this->handler->getDlqQueue());
    }

    #[Test]
    public function canBeConfigure(): void
    {
        $this->handler->configure('dlx.exchange', 'dlq.queue');

        $this->assertTrue($this->handler->isConfigured());
        $this->assertSame('dlx.exchange', $this->handler->getDlqExchange());
        $this->assertSame('dlq.queue', $this->handler->getDlqQueue());
    }

    #[Test]
    public function handleRejectionThrowsWhenNotConfigured(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('DeadLetterHandler is not configured');

        $envelope = $this->createEnvelope('msg-1');
        $this->handler->handleRejection($envelope, new \RuntimeException('fail'));
    }

    #[Test]
    public function handleRejectionRejectsAndPublishesToDlq(): void
    {
        $this->handler->configure('dlx.exchange', 'dlq.queue');

        $envelope = $this->createEnvelope('msg-1');
        $reason = new \RuntimeException('Processing failed');

        $this->handler->handleRejection($envelope, $reason);

        // Envelope should be rejected without requeue
        $this->assertTrue($this->transport->isRejected('msg-1'));
        $this->assertFalse($this->transport->wasRejectedWithRequeue('msg-1'));

        // Envelope should be published to DLQ channel
        $dlqChannel = 'dlx.exchange.dlq.queue';
        $published = $this->transport->getPublishedOn($dlqChannel);
        $this->assertCount(1, $published);
        $this->assertSame($envelope, $published[0]);
    }

    #[Test]
    public function tracksDeadLetters(): void
    {
        $this->handler->configure('dlx', 'dlq');

        $envelope1 = $this->createEnvelope('msg-1');
        $reason1 = new \RuntimeException('Error 1');

        $envelope2 = $this->createEnvelope('msg-2');
        $reason2 = new \RuntimeException('Error 2');

        $this->handler->handleRejection($envelope1, $reason1);
        $this->handler->handleRejection($envelope2, $reason2);

        $this->assertSame(2, $this->handler->getDeadLetterCount());

        $deadLetters = $this->handler->getDeadLetters();
        $this->assertSame($envelope1, $deadLetters[0]['envelope']);
        $this->assertSame($reason1, $deadLetters[0]['reason']);
        $this->assertSame($envelope2, $deadLetters[1]['envelope']);
        $this->assertSame($reason2, $deadLetters[1]['reason']);
    }

    #[Test]
    public function deadLetterCountStartsAtZero(): void
    {
        $this->assertSame(0, $this->handler->getDeadLetterCount());
        $this->assertSame([], $this->handler->getDeadLetters());
    }

    private function createEnvelope(string $messageId): MessageEnvelopeInterface
    {
        $envelope = $this->createStub(MessageEnvelopeInterface::class);
        $envelope->method('getMessageId')->willReturn($messageId);
        $envelope->method('getMessageType')->willReturn('test.event');
        $envelope->method('getSchemaVersion')->willReturn('1.0');
        $envelope->method('getCorrelationId')->willReturn('corr-' . $messageId);
        $envelope->method('getCausationId')->willReturn(null);
        $envelope->method('getPayload')->willReturn(['data' => $messageId]);
        $envelope->method('getHeaders')->willReturn([]);
        $envelope->method('getTimestamp')->willReturn(new \DateTimeImmutable());
        $envelope->method('getAttempt')->willReturn(1);

        return $envelope;
    }
}

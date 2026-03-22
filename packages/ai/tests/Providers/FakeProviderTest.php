<?php

declare(strict_types=1);

namespace Lattice\Ai\Tests\Providers;

use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Responses\AiResponse;
use Lattice\Ai\Responses\FinishReason;
use Lattice\Ai\Responses\Usage;
use Lattice\Ai\Testing\FakeProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeProviderTest extends TestCase
{
    private function createResponse(string $content = 'Test response'): AiResponse
    {
        return new AiResponse(
            content: $content,
            usage: new Usage(10, 20),
            finishReason: FinishReason::Stop,
        );
    }

    #[Test]
    public function it_returns_queued_responses_in_order(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse('First'));
        $fake->addResponse($this->createResponse('Second'));

        $r1 = $fake->chat([UserMessage::create('a')]);
        $r2 = $fake->chat([UserMessage::create('b')]);

        $this->assertSame('First', $r1->getText());
        $this->assertSame('Second', $r2->getText());
    }

    #[Test]
    public function it_cycles_responses_when_exhausted(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse('Only one'));

        $r1 = $fake->chat([UserMessage::create('a')]);
        $r2 = $fake->chat([UserMessage::create('b')]);

        $this->assertSame('Only one', $r1->getText());
        $this->assertSame('Only one', $r2->getText());
    }

    #[Test]
    public function it_returns_default_response_when_queue_empty(): void
    {
        $fake = new FakeProvider();
        $response = $fake->chat([UserMessage::create('test')]);

        $this->assertSame('', $response->getText());
        $this->assertSame(FinishReason::Stop, $response->finishReason);
    }

    #[Test]
    public function it_records_all_calls(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());

        $fake->chat([UserMessage::create('first')], ['model' => 'gpt-4o']);
        $fake->chat([UserMessage::create('second')], ['temperature' => 0.5]);

        $this->assertSame(2, $fake->callCount());
        $recorded = $fake->recorded();
        $this->assertCount(2, $recorded);
        $this->assertSame('gpt-4o', $recorded[0]['options']['model']);
        $this->assertSame(0.5, $recorded[1]['options']['temperature']);
    }

    #[Test]
    public function it_streams_response_as_chunks(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse('Hello beautiful world'));

        $chunks = iterator_to_array($fake->stream([UserMessage::create('test')]));

        $this->assertCount(3, $chunks); // "Hello", " beautiful", " world"
        $this->assertSame('Hello', $chunks[0]->delta);
        $this->assertFalse($chunks[0]->isFinal);
        $this->assertSame(' beautiful', $chunks[1]->delta);
        $this->assertFalse($chunks[1]->isFinal);
        $this->assertSame(' world', $chunks[2]->delta);
        $this->assertTrue($chunks[2]->isFinal);
    }

    #[Test]
    public function it_asserts_prompt_contains(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('What is PHP?')]);

        // Should not throw
        $fake->assertPromptContains('PHP');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_when_prompt_does_not_contain(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('Hello')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected prompt to contain [missing]');

        $fake->assertPromptContains('missing');
    }

    #[Test]
    public function it_asserts_prompt_not_contains(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('Hello')]);

        // Should not throw
        $fake->assertPromptNotContains('missing');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_when_prompt_unexpectedly_contains(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('Hello')]);

        $this->expectException(\RuntimeException::class);

        $fake->assertPromptNotContains('Hello');
    }

    #[Test]
    public function it_asserts_call_count(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());

        $fake->chat([UserMessage::create('a')]);
        $fake->chat([UserMessage::create('b')]);

        // Should not throw
        $fake->assertCallCount(2);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_on_wrong_call_count(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('a')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected [5] calls, but [1] were made.');

        $fake->assertCallCount(5);
    }

    #[Test]
    public function it_asserts_nothing_sent(): void
    {
        $fake = new FakeProvider();

        // Should not throw
        $fake->assertNothingSent();
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_when_something_was_sent(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('hi')]);

        $this->expectException(\RuntimeException::class);

        $fake->assertNothingSent();
    }

    #[Test]
    public function it_asserts_model_used(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('test')], ['model' => 'gpt-4o']);

        // Should not throw
        $fake->assertModelUsed('gpt-4o');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_when_model_not_used(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([UserMessage::create('test')], ['model' => 'gpt-4o']);

        $this->expectException(\RuntimeException::class);

        $fake->assertModelUsed('claude-3');
    }

    #[Test]
    public function it_searches_system_messages_too(): void
    {
        $fake = new FakeProvider();
        $fake->addResponse($this->createResponse());
        $fake->chat([
            SystemMessage::create('You are a helpful assistant.'),
            UserMessage::create('Hi'),
        ]);

        // Should find text in system message
        $fake->assertPromptContains('helpful assistant');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_adds_multiple_responses_at_once(): void
    {
        $fake = new FakeProvider();
        $fake->addResponses([
            $this->createResponse('First'),
            $this->createResponse('Second'),
        ]);

        $r1 = $fake->chat([UserMessage::create('a')]);
        $r2 = $fake->chat([UserMessage::create('b')]);

        $this->assertSame('First', $r1->getText());
        $this->assertSame('Second', $r2->getText());
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Ai\Tests;

use Lattice\Ai\AiManager;
use Lattice\Ai\Exceptions\ProviderNotFoundException;
use Lattice\Ai\Messages\UserMessage;
use Lattice\Ai\Responses\AiResponse;
use Lattice\Ai\Responses\FinishReason;
use Lattice\Ai\Responses\Usage;
use Lattice\Ai\Testing\FakeProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AiManagerTest extends TestCase
{
    #[Test]
    public function it_registers_and_retrieves_a_provider(): void
    {
        $manager = new AiManager('fake');
        $fake = new FakeProvider();
        $manager->register('fake', $fake);

        $this->assertSame($fake, $manager->provider('fake'));
    }

    #[Test]
    public function it_returns_default_provider(): void
    {
        $manager = new AiManager('fake');
        $fake = new FakeProvider();
        $manager->register('fake', $fake);

        $this->assertSame($fake, $manager->getDefaultProvider());
    }

    #[Test]
    public function it_throws_for_unknown_provider(): void
    {
        $manager = new AiManager('openai');

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage('AI provider [openai] is not registered.');

        $manager->provider('openai');
    }

    #[Test]
    public function it_delegates_chat_to_default_provider(): void
    {
        $manager = new AiManager('fake');
        $fake = new FakeProvider();
        $fake->addResponse(new AiResponse(
            content: 'Hello from fake!',
            usage: new Usage(10, 20),
            finishReason: FinishReason::Stop,
        ));
        $manager->register('fake', $fake);

        $messages = [UserMessage::create('Hi')];
        $response = $manager->chat($messages);

        $this->assertSame('Hello from fake!', $response->getText());
        $this->assertSame(10, $response->getUsage()->promptTokens);
        $this->assertSame(20, $response->getUsage()->completionTokens);
        $this->assertSame(30, $response->getUsage()->totalTokens);
        $this->assertTrue($response->isComplete());
    }

    #[Test]
    public function it_delegates_stream_to_default_provider(): void
    {
        $manager = new AiManager('fake');
        $fake = new FakeProvider();
        $fake->addResponse(new AiResponse(
            content: 'Hello world',
            usage: new Usage(5, 10),
            finishReason: FinishReason::Stop,
        ));
        $manager->register('fake', $fake);

        $messages = [UserMessage::create('Hi')];
        $chunks = iterator_to_array($manager->stream($messages));

        $this->assertNotEmpty($chunks);
        $lastChunk = end($chunks);
        $this->assertTrue($lastChunk->isFinal);
    }

    #[Test]
    public function it_switches_provider_with_using(): void
    {
        $manager = new AiManager('fake1');
        $fake1 = new FakeProvider();
        $fake2 = new FakeProvider();
        $manager->register('fake1', $fake1);
        $manager->register('fake2', $fake2);

        $switched = $manager->using('fake2');
        $this->assertSame($fake2, $switched->getDefaultProvider());

        // Original manager is unchanged
        $this->assertSame($fake1, $manager->getDefaultProvider());
    }

    #[Test]
    public function it_lists_registered_providers(): void
    {
        $manager = new AiManager();
        $manager->register('openai', new FakeProvider());
        $manager->register('anthropic', new FakeProvider());

        $names = $manager->registeredProviders();
        $this->assertContains('openai', $names);
        $this->assertContains('anthropic', $names);
    }

    #[Test]
    public function it_checks_if_provider_exists(): void
    {
        $manager = new AiManager();
        $manager->register('openai', new FakeProvider());

        $this->assertTrue($manager->hasProvider('openai'));
        $this->assertFalse($manager->hasProvider('gemini'));
    }

    #[Test]
    public function it_fakes_all_providers(): void
    {
        $manager = new AiManager('openai');
        $manager->register('openai', new FakeProvider());
        $manager->register('anthropic', new FakeProvider());

        $fake = $manager->fake();
        $fake->addResponse(new AiResponse(
            content: 'Faked response',
            usage: new Usage(1, 2),
            finishReason: FinishReason::Stop,
        ));

        $response = $manager->chat([UserMessage::create('test')]);
        $this->assertSame('Faked response', $response->getText());
    }
}

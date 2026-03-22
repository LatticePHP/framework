<?php

declare(strict_types=1);

namespace Lattice\Ai\Tests\Messages;

use Lattice\Ai\Messages\AssistantMessage;
use Lattice\Ai\Messages\SystemMessage;
use Lattice\Ai\Messages\ToolCall;
use Lattice\Ai\Messages\ToolResult;
use Lattice\Ai\Messages\UserMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    #[Test]
    public function it_creates_user_message_with_text(): void
    {
        $message = UserMessage::create('Hello, AI!');

        $this->assertSame('user', $message->role());
        $this->assertSame('Hello, AI!', $message->content);
    }

    #[Test]
    public function it_creates_user_message_with_images(): void
    {
        $message = UserMessage::withImages('Describe this', ['https://example.com/img.jpg']);

        $this->assertSame('user', $message->role());
        $this->assertIsArray($message->content);
        $this->assertCount(2, $message->content);
        $this->assertSame('text', $message->content[0]['type']);
        $this->assertSame('image_url', $message->content[1]['type']);
    }

    #[Test]
    public function it_serializes_user_message_to_array(): void
    {
        $message = UserMessage::create('Test');
        $array = $message->toArray();

        $this->assertSame('user', $array['role']);
        $this->assertSame('Test', $array['content']);
    }

    #[Test]
    public function it_deserializes_user_message_from_array(): void
    {
        $original = UserMessage::create('Round trip');
        $array = $original->toArray();
        $restored = UserMessage::fromArray($array);

        $this->assertSame($original->content, $restored->content);
        $this->assertSame($original->role(), $restored->role());
    }

    #[Test]
    public function it_creates_assistant_message(): void
    {
        $message = AssistantMessage::create('I am an AI assistant.');

        $this->assertSame('assistant', $message->role());
        $this->assertSame('I am an AI assistant.', $message->content);
        $this->assertFalse($message->hasToolCalls());
    }

    #[Test]
    public function it_creates_assistant_message_with_tool_calls(): void
    {
        $toolCall = new ToolCall('tc_1', 'getWeather', ['city' => 'London']);
        $message = AssistantMessage::withToolCalls('', [$toolCall]);

        $this->assertTrue($message->hasToolCalls());
        $this->assertCount(1, $message->toolCalls);
        $this->assertSame('getWeather', $message->toolCalls[0]->name);
    }

    #[Test]
    public function it_serializes_assistant_message_with_tool_calls(): void
    {
        $toolCall = new ToolCall('tc_1', 'getWeather', ['city' => 'London']);
        $message = AssistantMessage::withToolCalls('Checking weather', [$toolCall]);
        $array = $message->toArray();

        $this->assertSame('assistant', $array['role']);
        $this->assertSame('Checking weather', $array['content']);
        $this->assertCount(1, $array['tool_calls']);
        $this->assertSame('getWeather', $array['tool_calls'][0]['name']);
    }

    #[Test]
    public function it_deserializes_assistant_message_from_array(): void
    {
        $original = AssistantMessage::withToolCalls('Result', [
            new ToolCall('tc_1', 'search', ['q' => 'PHP']),
        ]);
        $array = $original->toArray();
        $restored = AssistantMessage::fromArray($array);

        $this->assertSame($original->content, $restored->content);
        $this->assertCount(1, $restored->toolCalls);
        $this->assertSame('search', $restored->toolCalls[0]->name);
    }

    #[Test]
    public function it_creates_system_message(): void
    {
        $message = SystemMessage::create('You are a helpful assistant.');

        $this->assertSame('system', $message->role());
        $this->assertSame('You are a helpful assistant.', $message->content);
    }

    #[Test]
    public function it_serializes_system_message_roundtrip(): void
    {
        $original = SystemMessage::create('System prompt');
        $array = $original->toArray();
        $restored = SystemMessage::fromArray($array);

        $this->assertSame('system', $array['role']);
        $this->assertSame($original->content, $restored->content);
    }

    #[Test]
    public function it_creates_tool_call(): void
    {
        $toolCall = new ToolCall('tc_abc', 'getWeather', ['city' => 'NYC']);

        $this->assertSame('tc_abc', $toolCall->id);
        $this->assertSame('getWeather', $toolCall->name);
        $this->assertSame(['city' => 'NYC'], $toolCall->arguments);
    }

    #[Test]
    public function it_serializes_tool_call_roundtrip(): void
    {
        $original = new ToolCall('tc_1', 'calculate', ['expr' => '2+2']);
        $array = $original->toArray();
        $restored = ToolCall::fromArray($array);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->name, $restored->name);
        $this->assertSame($original->arguments, $restored->arguments);
    }

    #[Test]
    public function it_creates_tool_result(): void
    {
        $result = new ToolResult('tc_1', '{"temperature": 22}');

        $this->assertSame('tool', $result->role());
        $this->assertSame('tc_1', $result->toolCallId);
        $this->assertSame('{"temperature": 22}', $result->content);
        $this->assertFalse($result->isError);
    }

    #[Test]
    public function it_creates_error_tool_result(): void
    {
        $result = new ToolResult('tc_2', 'API unavailable', true);

        $this->assertTrue($result->isError);
    }

    #[Test]
    public function it_serializes_tool_result_roundtrip(): void
    {
        $original = new ToolResult('tc_1', 'some result', false);
        $array = $original->toArray();
        $restored = ToolResult::fromArray($array);

        $this->assertSame($original->toolCallId, $restored->toolCallId);
        $this->assertSame($original->content, $restored->content);
        $this->assertSame($original->isError, $restored->isError);
    }
}

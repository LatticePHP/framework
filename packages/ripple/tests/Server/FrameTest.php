<?php

declare(strict_types=1);

namespace Lattice\Ripple\Tests\Server;

use InvalidArgumentException;
use Lattice\Ripple\Server\Frame;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrameTest extends TestCase
{
    // --- Encoding tests ---

    public function test_encode_text_frame_with_small_payload(): void
    {
        $payload = 'Hello';
        $frame = Frame::encode(Frame::OPCODE_TEXT, $payload);

        // Byte 0: FIN=1, opcode=0x1 => 0x81
        $this->assertSame(0x81, ord($frame[0]));
        // Byte 1: mask=0, length=5
        $this->assertSame(5, ord($frame[1]));
        // Payload
        $this->assertSame('Hello', substr($frame, 2));
    }

    public function test_encode_binary_frame(): void
    {
        $payload = "\x00\x01\x02\x03";
        $frame = Frame::encode(Frame::OPCODE_BINARY, $payload);

        $this->assertSame(0x82, ord($frame[0]));
        $this->assertSame(4, ord($frame[1]));
        $this->assertSame($payload, substr($frame, 2));
    }

    public function test_encode_16bit_payload_length(): void
    {
        $payload = str_repeat('A', 200);
        $frame = Frame::encode(Frame::OPCODE_TEXT, $payload);

        $this->assertSame(0x81, ord($frame[0]));
        // Length indicator 126 => 16-bit extended
        $this->assertSame(126, ord($frame[1]));
        // 16-bit length in network byte order
        $this->assertSame(200, unpack('n', substr($frame, 2, 2))[1]);
        $this->assertSame($payload, substr($frame, 4));
    }

    public function test_encode_64bit_payload_length(): void
    {
        $payload = str_repeat('B', 70000);
        $frame = Frame::encode(Frame::OPCODE_TEXT, $payload);

        $this->assertSame(0x81, ord($frame[0]));
        // Length indicator 127 => 64-bit extended
        $this->assertSame(127, ord($frame[1]));
        $this->assertSame(70000, unpack('J', substr($frame, 2, 8))[1]);
        $this->assertSame($payload, substr($frame, 10));
    }

    public function test_encode_non_fin_frame(): void
    {
        $frame = Frame::encode(Frame::OPCODE_TEXT, 'part1', fin: false);

        // FIN=0, opcode=0x1 => 0x01
        $this->assertSame(0x01, ord($frame[0]));
    }

    public function test_encode_masked_frame(): void
    {
        $payload = 'Hello';
        $frame = Frame::encode(Frame::OPCODE_TEXT, $payload, mask: true);

        // Byte 1 should have mask bit set
        $this->assertSame(0x80 | 5, ord($frame[1]));
        // Frame should be 2 + 4 (mask key) + 5 (payload) = 11 bytes
        $this->assertSame(11, strlen($frame));

        // Decode the masked frame and verify payload
        $maskKey = substr($frame, 2, 4);
        $maskedPayload = substr($frame, 6);
        $unmasked = Frame::applyMask($maskedPayload, $maskKey);
        $this->assertSame('Hello', $unmasked);
    }

    public function test_encode_ping_frame(): void
    {
        $frame = Frame::ping('heartbeat');

        $this->assertSame(0x89, ord($frame[0])); // FIN + PING
        $this->assertSame(9, ord($frame[1])); // length of 'heartbeat'
    }

    public function test_encode_pong_frame(): void
    {
        $frame = Frame::pong('heartbeat');

        $this->assertSame(0x8A, ord($frame[0])); // FIN + PONG
    }

    public function test_encode_close_frame(): void
    {
        $frame = Frame::close(Frame::CLOSE_NORMAL, 'goodbye');

        $this->assertSame(0x88, ord($frame[0])); // FIN + CLOSE
        // Payload: 2 bytes for code + 7 bytes for 'goodbye'
        $this->assertSame(9, ord($frame[1]));
    }

    // --- Decoding tests ---

    public function test_decode_unmasked_text_frame(): void
    {
        $raw = Frame::encode(Frame::OPCODE_TEXT, 'Hello');
        [$frame, $consumed] = Frame::decode($raw);

        $this->assertTrue($frame->fin);
        $this->assertSame(Frame::OPCODE_TEXT, $frame->opcode);
        $this->assertFalse($frame->masked);
        $this->assertSame('Hello', $frame->payload);
        $this->assertSame(strlen($raw), $consumed);
    }

    public function test_decode_masked_text_frame(): void
    {
        $payload = 'Hello';
        $maskKey = "\x37\xfa\x21\x3d";
        $maskedPayload = Frame::applyMask($payload, $maskKey);

        // Build masked frame manually
        $raw = chr(0x81) . chr(0x80 | 5) . $maskKey . $maskedPayload;

        [$frame, $consumed] = Frame::decode($raw);

        $this->assertTrue($frame->fin);
        $this->assertSame(Frame::OPCODE_TEXT, $frame->opcode);
        $this->assertTrue($frame->masked);
        $this->assertSame('Hello', $frame->payload);
        $this->assertSame(strlen($raw), $consumed);
    }

    public function test_decode_16bit_length_frame(): void
    {
        $payload = str_repeat('C', 300);
        $raw = Frame::encode(Frame::OPCODE_TEXT, $payload);

        [$frame, $consumed] = Frame::decode($raw, 65536);

        $this->assertSame($payload, $frame->payload);
        $this->assertSame(strlen($raw), $consumed);
    }

    public function test_decode_64bit_length_frame(): void
    {
        $payload = str_repeat('D', 70000);
        $raw = Frame::encode(Frame::OPCODE_TEXT, $payload);

        [$frame, $consumed] = Frame::decode($raw, 100000);

        $this->assertSame($payload, $frame->payload);
        $this->assertSame(strlen($raw), $consumed);
    }

    public function test_decode_ping_frame(): void
    {
        $raw = Frame::ping('test');
        [$frame, ] = Frame::decode($raw);

        $this->assertSame(Frame::OPCODE_PING, $frame->opcode);
        $this->assertSame('test', $frame->payload);
    }

    public function test_decode_pong_frame(): void
    {
        $raw = Frame::pong('test');
        [$frame, ] = Frame::decode($raw);

        $this->assertSame(Frame::OPCODE_PONG, $frame->opcode);
        $this->assertSame('test', $frame->payload);
    }

    public function test_decode_close_frame(): void
    {
        $raw = Frame::close(Frame::CLOSE_NORMAL, 'bye');
        [$frame, ] = Frame::decode($raw);

        $this->assertSame(Frame::OPCODE_CLOSE, $frame->opcode);

        [$code, $reason] = Frame::parseClosePayload($frame->payload);
        $this->assertSame(Frame::CLOSE_NORMAL, $code);
        $this->assertSame('bye', $reason);
    }

    public function test_decode_close_frame_without_reason(): void
    {
        $raw = Frame::close(Frame::CLOSE_GOING_AWAY);
        [$frame, ] = Frame::decode($raw);

        [$code, $reason] = Frame::parseClosePayload($frame->payload);
        $this->assertSame(Frame::CLOSE_GOING_AWAY, $code);
        $this->assertSame('', $reason);
    }

    public function test_parse_close_payload_with_empty_data(): void
    {
        [$code, $reason] = Frame::parseClosePayload('');
        $this->assertSame(Frame::CLOSE_NO_STATUS, $code);
        $this->assertSame('', $reason);
    }

    public function test_decode_non_fin_frame(): void
    {
        $raw = Frame::encode(Frame::OPCODE_TEXT, 'fragment', fin: false);
        [$frame, ] = Frame::decode($raw);

        $this->assertFalse($frame->fin);
        $this->assertSame(Frame::OPCODE_TEXT, $frame->opcode);
        $this->assertSame('fragment', $frame->payload);
    }

    // --- Error cases ---

    public function test_decode_insufficient_data_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient data for frame header.');

        Frame::decode("\x81");
    }

    public function test_decode_insufficient_data_for_16bit_length(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient data for 16-bit extended payload length.');

        // Byte 0: FIN + TEXT, Byte 1: length=126 (needs 2 more bytes)
        Frame::decode(chr(0x81) . chr(126));
    }

    public function test_decode_insufficient_data_for_64bit_length(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient data for 64-bit extended payload length.');

        // Byte 0: FIN + TEXT, Byte 1: length=127 (needs 8 more bytes)
        Frame::decode(chr(0x81) . chr(127) . "\x00\x00");
    }

    public function test_decode_insufficient_data_for_masking_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient data for masking key.');

        // Masked frame with length=1 but no mask key provided
        Frame::decode(chr(0x81) . chr(0x81));
    }

    public function test_decode_insufficient_data_for_payload(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient data for payload.');

        // Unmasked text frame claiming 10 bytes but only 5 provided
        Frame::decode(chr(0x81) . chr(10) . 'short');
    }

    public function test_decode_reserved_opcode_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reserved opcode');

        // Opcode 0x3 is reserved
        Frame::decode(chr(0x83) . chr(0));
    }

    public function test_decode_payload_exceeds_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds maximum allowed size');

        $payload = str_repeat('X', 200);
        $raw = Frame::encode(Frame::OPCODE_TEXT, $payload);

        Frame::decode($raw, 100);
    }

    // --- Masking round-trip ---

    public function test_mask_unmask_round_trip(): void
    {
        $original = 'The quick brown fox jumps over the lazy dog';
        $maskKey = "\xAB\xCD\xEF\x01";

        $masked = Frame::applyMask($original, $maskKey);
        $this->assertNotSame($original, $masked);

        $unmasked = Frame::applyMask($masked, $maskKey);
        $this->assertSame($original, $unmasked);
    }

    public function test_mask_empty_payload(): void
    {
        $maskKey = "\x01\x02\x03\x04";
        $masked = Frame::applyMask('', $maskKey);
        $this->assertSame('', $masked);
    }

    // --- Helper method tests ---

    public function test_is_control_opcode(): void
    {
        $this->assertTrue(Frame::isControlOpcode(Frame::OPCODE_CLOSE));
        $this->assertTrue(Frame::isControlOpcode(Frame::OPCODE_PING));
        $this->assertTrue(Frame::isControlOpcode(Frame::OPCODE_PONG));
        $this->assertFalse(Frame::isControlOpcode(Frame::OPCODE_TEXT));
        $this->assertFalse(Frame::isControlOpcode(Frame::OPCODE_BINARY));
        $this->assertFalse(Frame::isControlOpcode(Frame::OPCODE_CONTINUATION));
    }

    public function test_is_data_opcode(): void
    {
        $this->assertTrue(Frame::isDataOpcode(Frame::OPCODE_TEXT));
        $this->assertTrue(Frame::isDataOpcode(Frame::OPCODE_BINARY));
        $this->assertTrue(Frame::isDataOpcode(Frame::OPCODE_CONTINUATION));
        $this->assertFalse(Frame::isDataOpcode(Frame::OPCODE_CLOSE));
        $this->assertFalse(Frame::isDataOpcode(Frame::OPCODE_PING));
    }

    // --- Static factory methods ---

    public function test_text_factory(): void
    {
        $frame = Frame::text('test message');
        [$decoded, ] = Frame::decode($frame);

        $this->assertSame(Frame::OPCODE_TEXT, $decoded->opcode);
        $this->assertSame('test message', $decoded->payload);
        $this->assertTrue($decoded->fin);
    }

    public function test_binary_factory(): void
    {
        $data = "\x00\xFF\x10\x20";
        $frame = Frame::binary($data);
        [$decoded, ] = Frame::decode($frame);

        $this->assertSame(Frame::OPCODE_BINARY, $decoded->opcode);
        $this->assertSame($data, $decoded->payload);
    }

    // --- Multiple frames in buffer ---

    public function test_decode_multiple_frames_from_buffer(): void
    {
        $frame1 = Frame::text('first');
        $frame2 = Frame::text('second');
        $buffer = $frame1 . $frame2;

        [$decoded1, $consumed1] = Frame::decode($buffer);
        $this->assertSame('first', $decoded1->payload);

        $remaining = substr($buffer, $consumed1);
        [$decoded2, $consumed2] = Frame::decode($remaining);
        $this->assertSame('second', $decoded2->payload);
    }

    // --- Encode/decode round-trip for all opcodes ---

    public function test_encode_decode_round_trip_all_opcodes(): void
    {
        $opcodes = [
            Frame::OPCODE_TEXT => 'text payload',
            Frame::OPCODE_BINARY => "\x00\x01\x02",
            Frame::OPCODE_PING => 'ping data',
            Frame::OPCODE_PONG => 'pong data',
        ];

        foreach ($opcodes as $opcode => $payload) {
            $encoded = Frame::encode($opcode, $payload);
            [$decoded, ] = Frame::decode($encoded);

            $this->assertSame($opcode, $decoded->opcode, "Opcode mismatch for {$opcode}");
            $this->assertSame($payload, $decoded->payload, "Payload mismatch for {$opcode}");
            $this->assertTrue($decoded->fin, "FIN should be true for {$opcode}");
        }
    }
}

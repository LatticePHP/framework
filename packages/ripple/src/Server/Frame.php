<?php

declare(strict_types=1);

namespace Lattice\Ripple\Server;

use InvalidArgumentException;
use RuntimeException;

/**
 * WebSocket frame encoder/decoder per RFC 6455 Section 5.
 *
 * Handles FIN bit, opcodes, masking, and all three payload length
 * encodings (7-bit, 16-bit extended, 64-bit extended).
 */
final class Frame
{
    public const OPCODE_CONTINUATION = 0x0;
    public const OPCODE_TEXT = 0x1;
    public const OPCODE_BINARY = 0x2;
    public const OPCODE_CLOSE = 0x8;
    public const OPCODE_PING = 0x9;
    public const OPCODE_PONG = 0xA;

    /** @var array<int> Reserved opcodes that must be rejected */
    private const RESERVED_OPCODES = [0x3, 0x4, 0x5, 0x6, 0x7, 0xB, 0xC, 0xD, 0xE, 0xF];

    public const CLOSE_NORMAL = 1000;
    public const CLOSE_GOING_AWAY = 1001;
    public const CLOSE_PROTOCOL_ERROR = 1002;
    public const CLOSE_UNSUPPORTED_DATA = 1003;
    public const CLOSE_NO_STATUS = 1005;
    public const CLOSE_ABNORMAL = 1006;
    public const CLOSE_INVALID_PAYLOAD = 1007;
    public const CLOSE_POLICY_VIOLATION = 1008;
    public const CLOSE_MESSAGE_TOO_BIG = 1009;
    public const CLOSE_MANDATORY_EXTENSION = 1010;
    public const CLOSE_INTERNAL_ERROR = 1011;

    public function __construct(
        public readonly bool $fin,
        public readonly int $opcode,
        public readonly bool $masked,
        public readonly string $payload,
        public readonly string $maskingKey = '',
    ) {}

    /**
     * Decode a raw WebSocket frame from binary data.
     *
     * Returns the decoded Frame and the number of bytes consumed.
     *
     * @return array{Frame, int} The decoded frame and bytes consumed.
     *
     * @throws RuntimeException If not enough data is available.
     * @throws InvalidArgumentException If the frame uses a reserved opcode.
     */
    public static function decode(string $data, int $maxPayloadSize = 65536): array
    {
        $dataLen = strlen($data);

        if ($dataLen < 2) {
            throw new RuntimeException('Insufficient data for frame header.');
        }

        $byte0 = ord($data[0]);
        $byte1 = ord($data[1]);

        $fin = ($byte0 & 0x80) !== 0;
        $opcode = $byte0 & 0x0F;
        $masked = ($byte1 & 0x80) !== 0;
        $payloadLength = $byte1 & 0x7F;

        if (in_array($opcode, self::RESERVED_OPCODES, true)) {
            throw new InvalidArgumentException(
                sprintf('Reserved opcode 0x%X is not allowed (RFC 6455).', $opcode),
            );
        }

        $offset = 2;

        if ($payloadLength === 126) {
            if ($dataLen < $offset + 2) {
                throw new RuntimeException('Insufficient data for 16-bit extended payload length.');
            }
            $payloadLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            if ($dataLen < $offset + 8) {
                throw new RuntimeException('Insufficient data for 64-bit extended payload length.');
            }
            $payloadLength = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }

        if ($payloadLength > $maxPayloadSize) {
            throw new InvalidArgumentException(
                sprintf(
                    'Payload length %d exceeds maximum allowed size of %d bytes.',
                    $payloadLength,
                    $maxPayloadSize,
                ),
            );
        }

        $maskingKey = '';
        if ($masked) {
            if ($dataLen < $offset + 4) {
                throw new RuntimeException('Insufficient data for masking key.');
            }
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
        }

        if ($dataLen < $offset + $payloadLength) {
            throw new RuntimeException('Insufficient data for payload.');
        }

        $payload = substr($data, $offset, $payloadLength);
        $offset += $payloadLength;

        if ($masked && $maskingKey !== '') {
            $payload = self::applyMask($payload, $maskingKey);
        }

        return [
            new self($fin, $opcode, $masked, $payload, $maskingKey),
            $offset,
        ];
    }

    /**
     * Encode this frame into raw binary data for transmission.
     *
     * Server-to-client frames MUST NOT be masked per RFC 6455.
     */
    public static function encode(int $opcode, string $payload, bool $fin = true, bool $mask = false): string
    {
        $frame = '';

        $byte0 = ($fin ? 0x80 : 0x00) | ($opcode & 0x0F);
        $frame .= chr($byte0);

        $payloadLength = strlen($payload);
        $maskBit = $mask ? 0x80 : 0x00;

        if ($payloadLength < 126) {
            $frame .= chr($maskBit | $payloadLength);
        } elseif ($payloadLength <= 65535) {
            $frame .= chr($maskBit | 126);
            $frame .= pack('n', $payloadLength);
        } else {
            $frame .= chr($maskBit | 127);
            $frame .= pack('J', $payloadLength);
        }

        if ($mask) {
            $maskingKey = random_bytes(4);
            $frame .= $maskingKey;
            $frame .= self::applyMask($payload, $maskingKey);
        } else {
            $frame .= $payload;
        }

        return $frame;
    }

    /**
     * Create a text frame.
     */
    public static function text(string $payload): string
    {
        return self::encode(self::OPCODE_TEXT, $payload);
    }

    /**
     * Create a binary frame.
     */
    public static function binary(string $payload): string
    {
        return self::encode(self::OPCODE_BINARY, $payload);
    }

    /**
     * Create a close frame with optional status code and reason.
     */
    public static function close(int $code = self::CLOSE_NORMAL, string $reason = ''): string
    {
        $payload = pack('n', $code) . $reason;

        return self::encode(self::OPCODE_CLOSE, $payload);
    }

    /**
     * Create a ping frame.
     */
    public static function ping(string $payload = ''): string
    {
        return self::encode(self::OPCODE_PING, $payload);
    }

    /**
     * Create a pong frame.
     */
    public static function pong(string $payload = ''): string
    {
        return self::encode(self::OPCODE_PONG, $payload);
    }

    /**
     * Parse close frame payload into code and reason.
     *
     * @return array{int, string} Close code and reason.
     */
    public static function parseClosePayload(string $payload): array
    {
        if (strlen($payload) < 2) {
            return [self::CLOSE_NO_STATUS, ''];
        }

        $code = unpack('n', substr($payload, 0, 2))[1];
        $reason = substr($payload, 2);

        return [$code, $reason];
    }

    /**
     * Apply XOR masking per RFC 6455 Section 5.3.
     *
     * The same function is used for both masking and unmasking since
     * XOR is its own inverse.
     */
    public static function applyMask(string $data, string $maskingKey): string
    {
        $length = strlen($data);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $data[$i] ^ $maskingKey[$i % 4];
        }

        return $result;
    }

    /**
     * Check if the opcode is a control frame.
     */
    public static function isControlOpcode(int $opcode): bool
    {
        return $opcode >= 0x8;
    }

    /**
     * Check if the opcode is a data frame.
     */
    public static function isDataOpcode(int $opcode): bool
    {
        return $opcode < 0x8;
    }
}

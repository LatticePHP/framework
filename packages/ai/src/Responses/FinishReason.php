<?php

declare(strict_types=1);

namespace Lattice\Ai\Responses;

enum FinishReason: string
{
    case Stop = 'stop';
    case Length = 'length';
    case ToolCall = 'tool_call';
    case ContentFilter = 'content_filter';
    case Error = 'error';
}

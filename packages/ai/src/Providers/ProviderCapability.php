<?php

declare(strict_types=1);

namespace Lattice\Ai\Providers;

enum ProviderCapability: string
{
    case Chat = 'chat';
    case Streaming = 'streaming';
    case ToolCalling = 'tool_calling';
    case StructuredOutput = 'structured_output';
    case Embeddings = 'embeddings';
    case ImageGeneration = 'image_generation';
    case AudioSynthesis = 'audio_synthesis';
    case AudioTranscription = 'audio_transcription';
    case Reranking = 'reranking';
    case Vision = 'vision';
}

import { useState } from 'react';
import { Button } from '@nextui-org/react';
import type { StackFrame } from '@/schemas/entry';

interface StackTraceProps {
  frames: StackFrame[];
  maxVisible?: number;
}

function isVendorFrame(frame: StackFrame): boolean {
  const file = frame.file ?? '';
  return file.includes('/vendor/') || file.includes('\\vendor\\');
}

function formatFrame(frame: StackFrame): string {
  const parts: string[] = [];

  if (frame.class) {
    parts.push(frame.class);
    parts.push(frame.type ?? '->');
    parts.push(frame.function ?? '');
  } else if (frame.function) {
    parts.push(frame.function);
  }

  if (frame.file) {
    parts.push(` (${frame.file}:${frame.line ?? '?'})`);
  }

  return parts.join('');
}

export default function StackTrace({ frames, maxVisible = 10 }: StackTraceProps) {
  const [expanded, setExpanded] = useState(false);

  const visibleFrames = expanded ? frames : frames.slice(0, maxVisible);
  const hasMore = frames.length > maxVisible;

  return (
    <div className="font-mono text-xs leading-relaxed">
      <ol className="list-none space-y-0.5">
        {visibleFrames.map((frame, i) => (
          <li
            key={i}
            className={`py-0.5 px-2 rounded ${
              isVendorFrame(frame)
                ? 'stack-frame-vendor text-default-400'
                : 'stack-frame-app text-foreground'
            } hover:bg-default-100 transition-colors`}
          >
            <span className="text-default-400 mr-2 select-none">#{i}</span>
            {frame.class && (
              <span className="text-primary">{frame.class}</span>
            )}
            {frame.type && (
              <span className="text-default-500">{frame.type}</span>
            )}
            {frame.function && (
              <span className="text-warning">{frame.function}()</span>
            )}
            {frame.file && (
              <span className="text-default-400 ml-1">
                {frame.file}:{frame.line ?? '?'}
              </span>
            )}
          </li>
        ))}
      </ol>

      {hasMore && !expanded && (
        <Button
          size="sm"
          variant="light"
          onPress={() => setExpanded(true)}
          className="mt-2"
        >
          Show all {frames.length} frames
        </Button>
      )}
      {expanded && hasMore && (
        <Button
          size="sm"
          variant="light"
          onPress={() => setExpanded(false)}
          className="mt-2"
        >
          Collapse
        </Button>
      )}
    </div>
  );
}

// Re-export formatFrame for detail views
export { formatFrame, isVendorFrame };

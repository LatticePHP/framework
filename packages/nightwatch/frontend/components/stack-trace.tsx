"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import type { StackFrame } from "@/lib/schemas";

interface StackTraceProps {
  frames: StackFrame[];
  maxVisible?: number;
}

function isVendorFrame(frame: StackFrame): boolean {
  const file = frame.file ?? "";
  return file.includes("/vendor/") || file.includes("\\vendor\\");
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
                ? "stack-frame-vendor text-muted-foreground"
                : "stack-frame-app text-foreground"
            } hover:bg-muted transition-colors`}
          >
            <span className="text-muted-foreground mr-2 select-none">
              #{i}
            </span>
            {frame.class && (
              <span className="text-blue-500 dark:text-blue-400">
                {frame.class}
              </span>
            )}
            {frame.type && (
              <span className="text-muted-foreground">{frame.type}</span>
            )}
            {frame.function && (
              <span className="text-amber-500 dark:text-amber-400">
                {frame.function}()
              </span>
            )}
            {frame.file && (
              <span className="text-muted-foreground ml-1">
                {frame.file}:{frame.line ?? "?"}
              </span>
            )}
          </li>
        ))}
      </ol>

      {hasMore && !expanded && (
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setExpanded(true)}
          className="mt-2"
        >
          Show all {frames.length} frames
        </Button>
      )}
      {expanded && hasMore && (
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setExpanded(false)}
          className="mt-2"
        >
          Collapse
        </Button>
      )}
    </div>
  );
}

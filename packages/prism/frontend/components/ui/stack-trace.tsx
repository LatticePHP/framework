"use client";

import * as React from "react";
import { useState } from "react";
import { ChevronDown, ChevronRight, File, Code } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "./button";
import type { StackFrame } from "@/lib/schemas";

interface StackTraceProps {
  frames: StackFrame[];
  maxVisible?: number;
  className?: string;
}

function isVendorFrame(frame: StackFrame): boolean {
  const file = frame.file ?? "";
  return file.includes("/vendor/") || file.includes("\vendor\\") || file.includes("node_modules");
}

function shortPath(file: string): string {
  // Show last 3 path segments for readability
  const parts = file.replace(/\/g, "/").split("/");
  return parts.length > 3 ? ".../" + parts.slice(-3).join("/") : file;
}

function StackFrameRow({
  frame,
  index,
  isApp,
}: {
  frame: StackFrame;
  index: number;
  isApp: boolean;
}) {
  const [showContext, setShowContext] = useState(false);
  const hasContext = frame.code_context !== null && frame.code_context !== undefined;

  return (
    <li className="group">
      <div
        className={cn(
          "flex items-start gap-2 py-1.5 px-3 rounded-md transition-colors cursor-default",
          isApp
            ? "bg-card hover:bg-accent/50 text-foreground"
            : "text-muted-foreground hover:bg-muted/50"
        )}
        onClick={() => hasContext && setShowContext(!showContext)}
        role={hasContext ? "button" : undefined}
        tabIndex={hasContext ? 0 : undefined}
        onKeyDown={
          hasContext ? (e) => e.key === "Enter" && setShowContext(!showContext) : undefined
        }
      >
        <span className="text-muted-foreground select-none w-6 text-right shrink-0 text-xs leading-6">
          #{index}
        </span>

        <div className="flex-1 min-w-0 font-mono text-xs leading-6">
          {/* Class + function */}
          <span className="flex flex-wrap items-center gap-0.5">
            {frame.class && (
              <span className={cn("font-semibold", isApp ? "text-primary" : "text-muted-foreground")}>
                {frame.class}
              </span>
            )}
            {frame.class && frame.function && (
              <span className="text-muted-foreground">{"->"}</span>
            )}
            {frame.function && (
              <span className={cn("font-medium", isApp ? "text-amber-600 dark:text-amber-400" : "text-muted-foreground")}>
                {frame.function}()
              </span>
            )}
          </span>

          {/* File + line */}
          {frame.file && (
            <span className="flex items-center gap-1 text-muted-foreground mt-0.5">
              <File className="h-3 w-3 shrink-0" />
              <span className="truncate">{shortPath(frame.file)}</span>
              <span className="text-primary font-semibold shrink-0">:{frame.line}</span>
              {frame.column !== null && frame.column !== undefined && (
                <span className="text-muted-foreground shrink-0">:{frame.column}</span>
              )}
            </span>
          )}
        </div>

        {hasContext && (
          <span className="text-muted-foreground shrink-0 mt-1">
            {showContext ? (
              <ChevronDown className="h-3.5 w-3.5" />
            ) : (
              <ChevronRight className="h-3.5 w-3.5" />
            )}
          </span>
        )}
      </div>

      {/* Code context (expandable) */}
      {showContext && frame.code_context && (
        <div className="ml-9 mr-3 mt-1 mb-2 rounded-md border bg-muted/30 overflow-x-auto">
          <pre className="text-xs leading-5 font-mono">
            {frame.code_context.pre.map((line, i) => (
              <div key={`pre-${i}`} className="px-3 py-0.5 text-muted-foreground">
                <span className="inline-block w-10 text-right mr-3 select-none opacity-50">
                  {frame.line - frame.code_context!.pre.length + i}
                </span>
                {line}
              </div>
            ))}
            <div className="px-3 py-0.5 bg-primary/10 text-foreground font-semibold border-l-2 border-primary">
              <span className="inline-block w-10 text-right mr-3 select-none text-primary">
                {frame.line}
              </span>
              {frame.code_context.line}
            </div>
            {frame.code_context.post.map((line, i) => (
              <div key={`post-${i}`} className="px-3 py-0.5 text-muted-foreground">
                <span className="inline-block w-10 text-right mr-3 select-none opacity-50">
                  {frame.line + 1 + i}
                </span>
                {line}
              </div>
            ))}
          </pre>
        </div>
      )}
    </li>
  );
}

export default function StackTrace({ frames, maxVisible = 10, className }: StackTraceProps) {
  const [expanded, setExpanded] = useState(false);
  const [showVendor, setShowVendor] = useState(false);

  const filteredFrames = showVendor ? frames : frames.filter((f) => !isVendorFrame(f));
  const visibleFrames = expanded ? filteredFrames : filteredFrames.slice(0, maxVisible);
  const hasMore = filteredFrames.length > maxVisible;
  const vendorCount = frames.filter(isVendorFrame).length;

  if (frames.length === 0) {
    return (
      <div className={cn("text-sm text-muted-foreground italic p-4", className)}>
        No stack trace available.
      </div>
    );
  }

  return (
    <div className={cn("space-y-2", className)}>
      {/* Header controls */}
      <div className="flex items-center justify-between px-1">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <Code className="h-3.5 w-3.5" />
          <span>
            {frames.length} frame{frames.length !== 1 ? "s" : ""}
            {vendorCount > 0 && !showVendor && ` (${vendorCount} vendor hidden)`}
          </span>
        </div>
        {vendorCount > 0 && (
          <Button
            variant="ghost"
            size="sm"
            className="h-7 text-xs"
            onClick={() => setShowVendor(!showVendor)}
          >
            {showVendor ? "Hide vendor" : "Show vendor"}
          </Button>
        )}
      </div>

      {/* Frame list */}
      <ol className="list-none space-y-0.5">
        {visibleFrames.map((frame, i) => (
          <StackFrameRow
            key={i}
            frame={frame}
            index={i}
            isApp={!isVendorFrame(frame)}
          />
        ))}
      </ol>

      {/* Expand / collapse */}
      {hasMore && (
        <Button
          variant="ghost"
          size="sm"
          className="w-full text-xs"
          onClick={() => setExpanded(!expanded)}
        >
          {expanded
            ? "Collapse"
            : `Show all ${filteredFrames.length} frames`}
        </Button>
      )}
    </div>
  );
}

export { isVendorFrame, shortPath };

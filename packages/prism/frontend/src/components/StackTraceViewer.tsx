import { useState, useCallback, useMemo } from "react";
import { Button, Switch, Tooltip } from "@nextui-org/react";
import type { StackFrame } from "@/schemas/issue";

// ---------- helpers ----------

const VENDOR_PATTERNS = [
  /[/\\]vendor[/\\]/,
  /[/\\]node_modules[/\\]/,
  /^internal[/\\]/,
  /^node:/,
  /[/\\]\.next[/\\]/,
  /^webpack[/\\]/,
  /^<anonymous>/,
];

function isVendorFrame(frame: StackFrame): boolean {
  return VENDOR_PATTERNS.some((p) => p.test(frame.file));
}

/** Shorten long absolute paths for display: keep last 3 segments. */
function shortenPath(filePath: string): string {
  const parts = filePath.replace(/\\/g, "/").split("/");
  if (parts.length <= 4) return parts.join("/");
  return `.../${parts.slice(-3).join("/")}`;
}

/** Build a display label for a frame: class::method or function or (anonymous). */
function frameLabel(frame: StackFrame): string {
  if (frame.class && frame.function) return `${frame.class}::${frame.function}`;
  if (frame.function) return frame.function;
  return "(anonymous)";
}

// ---------- sub-components ----------

interface CodeContextBlockProps {
  context: NonNullable<StackFrame["code_context"]>;
  lineNumber: number;
}

function CodeContextBlock({ context, lineNumber }: CodeContextBlockProps) {
  const startLine = lineNumber - context.pre.length;

  return (
    <div className="overflow-x-auto rounded-b-lg border border-default-200 bg-default-50 dark:bg-default-50/50 font-mono text-xs leading-relaxed">
      <table className="w-full border-collapse">
        <tbody>
          {/* Pre-context lines */}
          {context.pre.map((codeLine, i) => {
            const ln = startLine + i;
            return (
              <tr key={`pre-${i}`} className="hover:bg-default-100/50">
                <td className="select-none border-r border-default-200 px-3 py-0.5 text-right text-default-400 w-14">
                  {ln}
                </td>
                <td className="px-4 py-0.5 whitespace-pre text-default-600">
                  {codeLine}
                </td>
              </tr>
            );
          })}

          {/* Current line — highlighted */}
          <tr className="bg-danger-50 dark:bg-danger-500/10">
            <td className="select-none border-r border-danger-200 dark:border-danger-500/30 px-3 py-0.5 text-right font-bold text-danger w-14">
              {lineNumber}
            </td>
            <td className="px-4 py-0.5 whitespace-pre font-semibold text-danger-700 dark:text-danger-400">
              {context.line}
            </td>
          </tr>

          {/* Post-context lines */}
          {context.post.map((codeLine, i) => {
            const ln = lineNumber + 1 + i;
            return (
              <tr key={`post-${i}`} className="hover:bg-default-100/50">
                <td className="select-none border-r border-default-200 px-3 py-0.5 text-right text-default-400 w-14">
                  {ln}
                </td>
                <td className="px-4 py-0.5 whitespace-pre text-default-600">
                  {codeLine}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

interface FrameRowProps {
  frame: StackFrame;
  index: number;
  isExpanded: boolean;
  isVendor: boolean;
  onToggle: () => void;
}

function FrameRow({
  frame,
  index,
  isExpanded,
  isVendor,
  onToggle,
}: FrameRowProps) {
  const hasContext = !!frame.code_context;

  return (
    <div
      className={`group ${isVendor ? "opacity-50 hover:opacity-80" : ""} transition-opacity`}
    >
      {/* Frame header row */}
      <button
        type="button"
        onClick={onToggle}
        disabled={!hasContext}
        className={`
          w-full flex items-center gap-3 px-4 py-2.5 text-left text-sm
          transition-colors
          ${hasContext ? "cursor-pointer hover:bg-default-100 dark:hover:bg-default-100/50" : "cursor-default"}
          ${isExpanded ? "bg-default-100 dark:bg-default-100/50" : ""}
          ${index > 0 ? "border-t border-default-100" : ""}
        `}
      >
        {/* Expand indicator */}
        <span
          className={`
            text-default-400 text-xs transition-transform w-4 flex-shrink-0 text-center
            ${hasContext ? "" : "invisible"}
            ${isExpanded ? "rotate-90" : ""}
          `}
        >
          {"\u25B6"}
        </span>

        {/* Frame index */}
        <span className="font-mono text-xs text-default-400 w-6 flex-shrink-0 text-right">
          {index}
        </span>

        {/* App / Vendor badge */}
        {isVendor ? (
          <Tooltip content="Vendor / library frame">
            <span className="flex-shrink-0 rounded bg-default-200 dark:bg-default-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-default-500">
              vendor
            </span>
          </Tooltip>
        ) : (
          <Tooltip content="Application frame">
            <span className="flex-shrink-0 rounded bg-primary-100 dark:bg-primary-500/20 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-primary">
              app
            </span>
          </Tooltip>
        )}

        {/* Function / method name */}
        <span className="font-mono font-medium text-foreground truncate">
          {frameLabel(frame)}
        </span>

        {/* Spacer */}
        <span className="flex-1" />

        {/* File path : line */}
        <span className="font-mono text-xs text-default-500 truncate max-w-[40%] text-right flex-shrink-0">
          <span className="text-cyan-600 dark:text-cyan-400">
            {shortenPath(frame.file)}
          </span>
          <span className="text-yellow-600 dark:text-yellow-400">
            :{frame.line}
          </span>
          {frame.column != null && (
            <span className="text-default-400">:{frame.column}</span>
          )}
        </span>
      </button>

      {/* Expanded code context */}
      {isExpanded && frame.code_context && (
        <div className="ml-[3.25rem] mr-4 mb-2">
          <CodeContextBlock
            context={frame.code_context}
            lineNumber={frame.line}
          />
        </div>
      )}
    </div>
  );
}

// ---------- main component ----------

interface StackTraceViewerProps {
  frames: StackFrame[];
  className?: string;
}

export function StackTraceViewer({ frames, className }: StackTraceViewerProps) {
  const [expandedFrames, setExpandedFrames] = useState<Set<number>>(() => {
    // Auto-expand first app frame that has code context
    const first = frames.findIndex(
      (f) => !isVendorFrame(f) && f.code_context != null,
    );
    return first >= 0 ? new Set([first]) : new Set();
  });

  const [showVendorFrames, setShowVendorFrames] = useState(false);

  const toggleFrame = useCallback((index: number) => {
    setExpandedFrames((prev) => {
      const next = new Set(prev);
      if (next.has(index)) {
        next.delete(index);
      } else {
        next.add(index);
      }
      return next;
    });
  }, []);

  const expandAllApp = useCallback(() => {
    const appIndices = frames
      .map((f, i) => (!isVendorFrame(f) && f.code_context ? i : -1))
      .filter((i) => i >= 0);
    setExpandedFrames(new Set(appIndices));
  }, [frames]);

  const collapseAll = useCallback(() => {
    setExpandedFrames(new Set());
  }, []);

  const { appCount, vendorCount } = useMemo(() => {
    let app = 0;
    let vendor = 0;
    for (const f of frames) {
      if (isVendorFrame(f)) vendor++;
      else app++;
    }
    return { appCount: app, vendorCount: vendor };
  }, [frames]);

  if (frames.length === 0) {
    return (
      <div
        className={`rounded-lg border border-default-200 p-6 text-center text-default-400 ${className ?? ""}`}
      >
        No stacktrace available
      </div>
    );
  }

  return (
    <div className={`rounded-lg border border-default-200 overflow-hidden ${className ?? ""}`}>
      {/* Toolbar */}
      <div className="flex items-center justify-between gap-3 border-b border-default-200 bg-default-50 dark:bg-default-50/50 px-4 py-2">
        <div className="flex items-center gap-4">
          <h3 className="text-sm font-semibold text-foreground">
            Stack Trace
          </h3>
          <span className="text-xs text-default-400">
            {appCount} app {appCount === 1 ? "frame" : "frames"}
            {vendorCount > 0 && (
              <>, {vendorCount} vendor</>
            )}
          </span>
        </div>

        <div className="flex items-center gap-3">
          {vendorCount > 0 && (
            <Switch
              size="sm"
              isSelected={showVendorFrames}
              onValueChange={setShowVendorFrames}
              classNames={{
                label: "text-xs text-default-500",
              }}
            >
              Show vendor
            </Switch>
          )}

          <div className="flex items-center gap-1">
            <Button
              size="sm"
              variant="flat"
              className="text-xs h-7"
              onPress={expandAllApp}
            >
              Expand app
            </Button>
            <Button
              size="sm"
              variant="flat"
              className="text-xs h-7"
              onPress={collapseAll}
            >
              Collapse
            </Button>
          </div>
        </div>
      </div>

      {/* Frame list */}
      <div className="divide-y-0">
        {frames.map((frame, index) => {
          const vendor = isVendorFrame(frame);
          if (vendor && !showVendorFrames) return null;

          return (
            <FrameRow
              key={index}
              frame={frame}
              index={index}
              isExpanded={expandedFrames.has(index)}
              isVendor={vendor}
              onToggle={() => toggleFrame(index)}
            />
          );
        })}

        {/* Collapsed vendor frame summary */}
        {!showVendorFrames && vendorCount > 0 && (
          <button
            type="button"
            onClick={() => setShowVendorFrames(true)}
            className="w-full border-t border-default-100 px-4 py-2 text-xs text-default-400 hover:text-default-600 hover:bg-default-50 transition-colors text-center"
          >
            {vendorCount} vendor {vendorCount === 1 ? "frame" : "frames"}{" "}
            hidden — click to show
          </button>
        )}
      </div>
    </div>
  );
}

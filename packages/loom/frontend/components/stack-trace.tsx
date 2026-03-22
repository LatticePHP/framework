interface TraceFrame {
  file?: string;
  line?: number;
  function?: string;
  class?: string;
}

interface StackTraceProps {
  exceptionClass?: string;
  exceptionMessage?: string;
  trace?: TraceFrame[];
}

export function StackTrace({
  exceptionClass,
  exceptionMessage,
  trace,
}: StackTraceProps) {
  if (!exceptionClass && !exceptionMessage) {
    return null;
  }

  return (
    <div className="space-y-3">
      <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
        <div className="font-mono text-sm font-semibold text-destructive">
          {exceptionClass ?? "Exception"}
        </div>
        {exceptionMessage && (
          <div className="mt-1 text-sm text-destructive/80">
            {exceptionMessage}
          </div>
        )}
      </div>

      {trace && trace.length > 0 && (
        <div className="overflow-hidden rounded-lg border">
          <div className="border-b bg-muted px-4 py-2">
            <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Stack Trace ({trace.length} frames)
            </span>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {trace.map((frame, index) => (
              <div
                key={index}
                className="border-b px-4 py-2 last:border-b-0 transition-colors hover:bg-muted/50"
              >
                <div className="flex items-baseline gap-2">
                  <span className="w-6 flex-shrink-0 text-right font-mono text-xs text-muted-foreground">
                    #{index}
                  </span>
                  <div className="min-w-0">
                    {(frame.class || frame.function) && (
                      <span className="font-mono text-xs">
                        {frame.class && (
                          <span className="text-primary">{frame.class}</span>
                        )}
                        {frame.class && frame.function && (
                          <span className="text-muted-foreground">::</span>
                        )}
                        {frame.function && (
                          <span className="text-amber-600 dark:text-amber-400">
                            {frame.function}()
                          </span>
                        )}
                      </span>
                    )}
                    {frame.file && (
                      <div className="truncate font-mono text-xs text-muted-foreground">
                        {frame.file}
                        {frame.line !== undefined && (
                          <span className="text-muted-foreground/70">
                            :{frame.line}
                          </span>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

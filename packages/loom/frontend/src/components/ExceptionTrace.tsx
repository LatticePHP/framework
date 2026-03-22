interface TraceFrame {
  file?: string;
  line?: number;
  function?: string;
  class?: string;
}

interface ExceptionTraceProps {
  exceptionClass?: string;
  exceptionMessage?: string;
  trace?: TraceFrame[];
}

export default function ExceptionTrace({
  exceptionClass,
  exceptionMessage,
  trace,
}: ExceptionTraceProps) {
  if (!exceptionClass && !exceptionMessage) {
    return null;
  }

  return (
    <div className="space-y-3">
      {/* Exception header */}
      <div className="bg-danger-50 dark:bg-danger-50/10 border border-danger-200 dark:border-danger-200/20 rounded-lg p-4">
        <div className="font-mono text-sm font-semibold text-danger">
          {exceptionClass ?? 'Exception'}
        </div>
        {exceptionMessage && (
          <div className="mt-1 text-sm text-danger-600 dark:text-danger-400">
            {exceptionMessage}
          </div>
        )}
      </div>

      {/* Stack trace */}
      {trace && trace.length > 0 && (
        <div className="bg-content2 rounded-lg overflow-hidden">
          <div className="px-4 py-2 border-b border-divider">
            <span className="text-xs font-semibold text-default-500 uppercase tracking-wide">
              Stack Trace ({trace.length} frames)
            </span>
          </div>
          <div className="max-h-96 overflow-y-auto trace-scroll">
            {trace.map((frame, index) => (
              <div
                key={index}
                className="px-4 py-2 border-b border-divider last:border-b-0 hover:bg-default-100 transition-colors"
              >
                <div className="flex items-baseline gap-2">
                  <span className="text-xs text-default-400 font-mono w-6 text-right flex-shrink-0">
                    #{index}
                  </span>
                  <div className="min-w-0">
                    {(frame.class || frame.function) && (
                      <span className="text-xs font-mono">
                        {frame.class && (
                          <span className="text-primary">{frame.class}</span>
                        )}
                        {frame.class && frame.function && (
                          <span className="text-default-400">::</span>
                        )}
                        {frame.function && (
                          <span className="text-warning">
                            {frame.function}()
                          </span>
                        )}
                      </span>
                    )}
                    {frame.file && (
                      <div className="text-xs text-default-500 font-mono truncate">
                        {frame.file}
                        {frame.line !== undefined && (
                          <span className="text-default-400">:{frame.line}</span>
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

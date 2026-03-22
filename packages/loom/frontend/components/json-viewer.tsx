"use client";

interface JsonViewerProps {
  data: unknown;
}

function formatJson(data: unknown): string {
  try {
    return JSON.stringify(data, null, 2);
  } catch {
    return String(data);
  }
}

export function JsonViewer({ data }: JsonViewerProps) {
  if (data === null || data === undefined) {
    return (
      <div className="text-sm italic text-muted-foreground">No payload data</div>
    );
  }

  return (
    <pre className="overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-muted p-4 font-mono text-xs">
      {formatJson(data)}
    </pre>
  );
}

"use client";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatMs, formatTime } from "@/lib/utils";
import type { Job, FailedJob } from "@/lib/schemas";

// --- Status Badge ---

const statusVariantMap: Record<
  string,
  "default" | "secondary" | "destructive" | "success" | "warning" | "outline"
> = {
  pending: "warning",
  processing: "default",
  completed: "success",
  failed: "destructive",
  retried: "secondary",
};

export function JobStatusBadge({ status }: { status: string }) {
  const variant = statusVariantMap[status] ?? "outline";
  return (
    <Badge variant={variant} className="capitalize">
      {status}
    </Badge>
  );
}

// --- Column Defs for recent jobs ---

export interface Column<T> {
  key: string;
  header: string;
  className?: string;
  render: (row: T) => React.ReactNode;
}

export const recentJobColumns: Column<Job>[] = [
  {
    key: "status",
    header: "Status",
    render: (row) => <JobStatusBadge status={row.status} />,
  },
  {
    key: "class",
    header: "Job Class",
    render: (row) => <span className="font-mono text-sm">{row.class}</span>,
  },
  {
    key: "queue",
    header: "Queue",
    render: (row) => (
      <span className="text-sm text-muted-foreground">{row.queue}</span>
    ),
  },
  {
    key: "runtime",
    header: "Runtime",
    render: (row) => (
      <span className="font-mono text-sm">{formatMs(row.runtime_ms)}</span>
    ),
  },
  {
    key: "attempts",
    header: "Attempts",
    render: (row) => <span className="text-sm">{row.attempts}</span>,
  },
  {
    key: "created",
    header: "Created",
    render: (row) => (
      <span className="text-xs text-muted-foreground">
        {formatTime(row.created_at)}
      </span>
    ),
  },
];

// --- Column Defs for failed jobs ---

export function failedJobColumns(options: {
  onRetry: (id: string) => void;
  onDelete: (id: string) => void;
  retryingId?: string | null;
}): Column<FailedJob>[] {
  return [
    {
      key: "class",
      header: "Job Class",
      render: (row) => <span className="font-mono text-sm">{row.class}</span>,
    },
    {
      key: "queue",
      header: "Queue",
      render: (row) => (
        <span className="text-sm text-muted-foreground">{row.queue}</span>
      ),
    },
    {
      key: "exception",
      header: "Exception",
      className: "max-w-xs",
      render: (row) => (
        <div className="max-w-xs truncate">
          <span className="font-mono text-xs text-destructive">
            {row.exception_class ?? "Unknown"}
          </span>
          {row.exception_message && (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">
              {row.exception_message}
            </p>
          )}
        </div>
      ),
    },
    {
      key: "attempts",
      header: "Attempts",
      render: (row) => <span className="text-sm">{row.attempts}</span>,
    },
    {
      key: "failed_at",
      header: "Failed At",
      render: (row) => (
        <span className="text-xs text-muted-foreground">
          {formatTime(row.failed_at)}
        </span>
      ),
    },
    {
      key: "actions",
      header: "Actions",
      render: (row) => (
        <div className="flex gap-1" onClick={(e) => e.stopPropagation()}>
          <Button
            size="sm"
            variant="outline"
            disabled={options.retryingId === row.id}
            onClick={() => options.onRetry(row.id)}
          >
            Retry
          </Button>
          <Button
            size="sm"
            variant="destructive"
            onClick={() => options.onDelete(row.id)}
          >
            Delete
          </Button>
        </div>
      ),
    },
  ];
}

"use client";

import { type ColumnDef } from "@tanstack/react-table";
import type { WorkflowSummary } from "@/lib/schemas";
import { StatusBadge } from "@/components/feedback/status-badge";
import { formatDuration, formatTimestamp, formatEventType, truncateId } from "@/lib/formatters";

export const workflowColumns: ColumnDef<WorkflowSummary>[] = [
  {
    accessorKey: "id",
    header: "ID",
    cell: ({ row }) => (
      <span className="font-mono text-xs" title={row.original.id}>
        {truncateId(row.original.id)}
      </span>
    ),
  },
  {
    accessorKey: "type",
    header: "Type",
    cell: ({ row }) => (
      <span className="text-sm font-medium">{row.original.type}</span>
    ),
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => <StatusBadge status={row.original.status} />,
  },
  {
    accessorKey: "started_at",
    header: "Started",
    cell: ({ row }) => (
      <span className="text-sm text-muted-foreground">
        {formatTimestamp(row.original.started_at)}
      </span>
    ),
  },
  {
    accessorKey: "duration_ms",
    header: "Duration",
    cell: ({ row }) => (
      <span className="text-sm font-mono">
        {formatDuration(row.original.duration_ms)}
      </span>
    ),
  },
  {
    accessorKey: "last_event_type",
    header: "Last Event",
    cell: ({ row }) => (
      <span className="text-xs text-muted-foreground">
        {formatEventType(row.original.last_event_type)}
      </span>
    ),
  },
];

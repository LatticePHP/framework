"use client";

import { type ColumnDef } from "@tanstack/react-table";
import { LevelBadge, StatusBadge } from "@/components/ui/status-badge";
import { Checkbox } from "@/components/ui/checkbox";
import { timeAgo } from "@/lib/utils";
import type { Issue } from "@/lib/schemas";

export const issueColumns: ColumnDef<Issue, unknown>[] = [
  {
    id: "select",
    header: ({ table }) => (
      <Checkbox
        checked={
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && "indeterminate")
        }
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label="Select all"
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label="Select row"
      />
    ),
    enableSorting: false,
    enableHiding: false,
    size: 40,
  },
  {
    accessorKey: "title",
    header: "Title",
    cell: ({ row }) => (
      <div className="max-w-md">
        <p className="font-medium truncate">{row.original.title}</p>
        {row.original.culprit && (
          <p className="text-xs text-muted-foreground truncate">{row.original.culprit}</p>
        )}
      </div>
    ),
  },
  {
    accessorKey: "level",
    header: "Level",
    cell: ({ row }) => <LevelBadge level={row.original.level} />,
    size: 100,
  },
  {
    accessorKey: "count",
    header: "Events",
    cell: ({ row }) => (
      <span className="font-mono text-sm">{row.original.count.toLocaleString()}</span>
    ),
    size: 80,
  },
  {
    accessorKey: "last_seen",
    header: "Last Seen",
    cell: ({ row }) => (
      <span className="text-sm text-muted-foreground" title={row.original.last_seen}>
        {timeAgo(row.original.last_seen)}
      </span>
    ),
    size: 120,
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => <StatusBadge status={row.original.status} />,
    size: 110,
  },
];

"use client";

import { useEffect, useState, useCallback } from "react";
import { Badge } from "@/components/ui/badge";
import { DataTable, type Column } from "@/components/data-table";
import { useLoomStore } from "@/lib/store";
import { apiGet } from "@/lib/api";
import { WorkersResponseSchema, type Worker } from "@/lib/schemas";
import { formatUptime, formatMemory } from "@/lib/utils";
import { Loader2 } from "lucide-react";
import { TimeSince } from "./time-since";

const statusVariantMap: Record<
  string,
  "default" | "success" | "destructive" | "secondary" | "warning" | "outline"
> = {
  active: "success",
  inactive: "secondary",
  stale: "destructive",
};

const workerColumns: Column<Worker>[] = [
  {
    key: "id",
    header: "Worker ID",
    render: (row) => <span className="font-mono text-xs">{row.id}</span>,
  },
  {
    key: "queue",
    header: "Queue",
    render: (row) => (
      <Badge variant="outline" className="text-xs">
        {row.queue}
      </Badge>
    ),
  },
  {
    key: "status",
    header: "Status",
    render: (row) => (
      <Badge
        variant={statusVariantMap[row.status] ?? "outline"}
        className="capitalize"
      >
        {row.status}
      </Badge>
    ),
  },
  {
    key: "pid",
    header: "PID",
    render: (row) => <span className="font-mono text-sm">{row.pid}</span>,
  },
  {
    key: "memory",
    header: "Memory",
    render: (row) => <span className="text-sm">{formatMemory(row.memory_mb)}</span>,
  },
  {
    key: "uptime",
    header: "Uptime",
    render: (row) => <span className="text-sm">{formatUptime(row.uptime)}</span>,
  },
  {
    key: "jobs_processed",
    header: "Jobs Processed",
    render: (row) => (
      <span className="font-mono text-sm">
        {row.jobs_processed.toLocaleString()}
      </span>
    ),
  },
  {
    key: "heartbeat",
    header: "Last Heartbeat",
    render: (row) => (
      <span className="text-xs text-muted-foreground">
        <TimeSince timestamp={row.last_heartbeat} />
      </span>
    ),
  },
];

export default function WorkersPage() {
  const refreshInterval = useLoomStore((s) => s.refreshInterval);
  const [workers, setWorkers] = useState<Worker[]>([]);
  const [loading, setLoading] = useState(true);
  const [fetching, setFetching] = useState(false);

  const fetchWorkers = useCallback(async () => {
    setFetching(true);
    try {
      const raw = await apiGet<unknown>("/workers");
      const parsed = WorkersResponseSchema.parse(raw);
      setWorkers(parsed.data);
    } catch {
      // silently ignore
    } finally {
      setLoading(false);
      setFetching(false);
    }
  }, []);

  useEffect(() => {
    void fetchWorkers();
  }, [fetchWorkers]);

  useEffect(() => {
    if (!refreshInterval) return;
    const id = setInterval(() => void fetchWorkers(), refreshInterval);
    return () => clearInterval(id);
  }, [refreshInterval, fetchWorkers]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Workers</h2>
        {fetching && !loading && (
          <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
        )}
      </div>

      <DataTable
        columns={workerColumns}
        data={workers}
        loading={loading}
        emptyMessage="No workers running"
        rowKey={(w) => w.id}
      />
    </div>
  );
}

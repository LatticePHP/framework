"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
import DurationBadge from "@/components/duration-badge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import type { BaseEntry, JobData } from "@/lib/schemas";

const statusVariants: Record<
  string,
  "success" | "warning" | "danger" | "info" | "default"
> = {
  completed: "success",
  processed: "success",
  queued: "info",
  processing: "warning",
  failed: "danger",
  retrying: "warning",
};

export default function JobsPage() {
  const { data, isLoading } = useEntries("job");
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const jData = (entry: BaseEntry): JobData =>
    entry.data as unknown as JobData;

  const shortClass = (cls: string) => {
    const parts = cls.split("\\");
    return parts[parts.length - 1] ?? cls;
  };

  const columns: ColumnDef[] = [
    {
      key: "job_class",
      label: "Job",
      render: (item) => (
        <div>
          <span className="text-sm font-semibold">
            {shortClass(jData(item).job_class)}
          </span>
          <p className="text-xs text-muted-foreground font-mono truncate max-w-md">
            {jData(item).job_class}
          </p>
        </div>
      ),
    },
    {
      key: "queue",
      label: "Queue",
      width: "100px",
      render: (item) => (
        <Badge variant="secondary">{jData(item).queue}</Badge>
      ),
    },
    {
      key: "status",
      label: "Status",
      width: "110px",
      render: (item) => (
        <Badge variant={statusVariants[jData(item).status] ?? "default"}>
          {jData(item).status}
        </Badge>
      ),
    },
    {
      key: "duration",
      label: "Duration",
      width: "90px",
      render: (item) =>
        jData(item).duration_ms != null ? (
          <DurationBadge ms={jData(item).duration_ms!} />
        ) : (
          <span className="text-xs text-muted-foreground">--</span>
        ),
    },
    {
      key: "attempt",
      label: "Attempt",
      width: "80px",
      render: (item) => (
        <span className="text-xs">
          {jData(item).attempt}
          {jData(item).max_tries != null ? `/${jData(item).max_tries}` : ""}
        </span>
      ),
    },
    {
      key: "timestamp",
      label: "Time",
      width: "120px",
      render: (item) => (
        <span className="text-xs text-muted-foreground">
          {new Date(item.timestamp).toLocaleTimeString()}
        </span>
      ),
    },
  ];

  const detail = selected ? jData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Jobs</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by job class..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent onClose={() => setSelected(null)} className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>
              {detail && shortClass(detail.job_class)}
            </DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground">Full Class</p>
                  <p className="text-xs font-mono">{detail.job_class}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Queue</p>
                  <p className="text-sm">{detail.queue}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Connection</p>
                  <p className="text-sm">{detail.connection}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Status</p>
                  <Badge
                    variant={statusVariants[detail.status] ?? "default"}
                  >
                    {detail.status}
                  </Badge>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Attempt</p>
                  <p className="text-sm">
                    {detail.attempt}
                    {detail.max_tries != null
                      ? ` / ${detail.max_tries}`
                      : ""}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Duration</p>
                  {detail.duration_ms != null ? (
                    <DurationBadge ms={detail.duration_ms} />
                  ) : (
                    <span className="text-sm text-muted-foreground">N/A</span>
                  )}
                </div>
              </div>

              {detail.payload &&
                Object.keys(detail.payload).length > 0 && (
                  <>
                    <Separator />
                    <div>
                      <p className="text-sm font-semibold mb-2">Payload</p>
                      <pre className="bg-muted rounded-lg p-3 text-xs font-mono overflow-x-auto">
                        {JSON.stringify(detail.payload, null, 2)}
                      </pre>
                    </div>
                  </>
                )}

              {detail.exception && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2 text-red-500 dark:text-red-400">
                      Exception
                    </p>
                    <p className="text-sm bg-red-50 dark:bg-red-950/30 rounded-lg p-3 text-red-600 dark:text-red-400 font-mono">
                      {detail.exception}
                    </p>
                  </div>
                </>
              )}
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setSelected(null)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

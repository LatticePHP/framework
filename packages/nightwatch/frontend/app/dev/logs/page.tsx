"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import type { BaseEntry, LogData } from "@/lib/schemas";
import { useFiltersStore } from "@/lib/store";

const levelVariants: Record<
  string,
  "success" | "info" | "warning" | "danger" | "secondary" | "default"
> = {
  debug: "default",
  info: "info",
  notice: "secondary",
  warning: "warning",
  error: "danger",
  critical: "danger",
  alert: "danger",
  emergency: "danger",
};

export default function LogsPage() {
  const { data, isLoading } = useEntries("log");
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const { levelFilter, setLevelFilter } = useFiltersStore();

  const lData = (entry: BaseEntry): LogData =>
    entry.data as unknown as LogData;

  const columns: ColumnDef[] = [
    {
      key: "level",
      label: "Level",
      width: "100px",
      render: (item) => (
        <Badge variant={levelVariants[lData(item).level] ?? "default"}>
          {lData(item).level.toUpperCase()}
        </Badge>
      ),
    },
    {
      key: "message",
      label: "Message",
      render: (item) => (
        <span className="text-sm truncate max-w-2xl block">
          {lData(item).message}
        </span>
      ),
    },
    {
      key: "channel",
      label: "Channel",
      width: "100px",
      render: (item) => (
        <span className="text-xs text-muted-foreground">
          {lData(item).channel}
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

  const detail = selected ? lData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold flex-1">Logs</h1>
        <Select
          value={levelFilter ?? ""}
          onValueChange={(v) => setLevelFilter(v || null)}
          className="w-[140px] h-9 text-sm"
        >
          <option value="">All Levels</option>
          {[
            "debug",
            "info",
            "notice",
            "warning",
            "error",
            "critical",
            "alert",
            "emergency",
          ].map((l) => (
            <option key={l} value={l}>
              {l.toUpperCase()}
            </option>
          ))}
        </Select>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by message..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent onClose={() => setSelected(null)} className="max-w-3xl">
          <DialogHeader>
            <DialogTitle className="flex gap-3 items-center">
              Log Entry
              {detail && (
                <Badge
                  variant={levelVariants[detail.level] ?? "default"}
                >
                  {detail.level.toUpperCase()}
                </Badge>
              )}
            </DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div>
                <p className="text-xs text-muted-foreground">Message</p>
                <p className="text-sm bg-muted rounded-lg p-3 whitespace-pre-wrap">
                  {detail.message}
                </p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground">Channel</p>
                  <p className="text-sm">{detail.channel}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Level</p>
                  <Badge
                    variant={levelVariants[detail.level] ?? "default"}
                  >
                    {detail.level.toUpperCase()}
                  </Badge>
                </div>
              </div>

              {detail.context &&
                Object.keys(detail.context).length > 0 && (
                  <div>
                    <p className="text-sm font-semibold mb-2">Context</p>
                    <pre className="bg-muted rounded-lg p-3 text-xs font-mono overflow-x-auto">
                      {JSON.stringify(detail.context, null, 2)}
                    </pre>
                  </div>
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

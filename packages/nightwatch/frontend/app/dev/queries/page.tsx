"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
import DurationBadge from "@/components/duration-badge";
import SqlHighlight from "@/components/sql-highlight";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import type { BaseEntry, QueryData } from "@/lib/schemas";
import { useFiltersStore } from "@/lib/store";

export default function QueriesPage() {
  const { data, isLoading } = useEntries("query");
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const { slowOnly, setSlowOnly } = useFiltersStore();

  const qData = (entry: BaseEntry): QueryData =>
    entry.data as unknown as QueryData;

  const columns: ColumnDef[] = [
    {
      key: "sql",
      label: "SQL",
      render: (item) => (
        <div className="max-w-lg">
          <SqlHighlight sql={qData(item).sql} truncate={120} />
        </div>
      ),
    },
    {
      key: "duration",
      label: "Duration",
      width: "100px",
      render: (item) => <DurationBadge ms={qData(item).duration_ms} />,
    },
    {
      key: "connection",
      label: "Connection",
      width: "100px",
      render: (item) => (
        <span className="text-xs text-muted-foreground">
          {qData(item).connection}
        </span>
      ),
    },
    {
      key: "badges",
      label: "Flags",
      width: "120px",
      render: (item) => (
        <div className="flex gap-1">
          {qData(item).slow && <Badge variant="danger">SLOW</Badge>}
          {qData(item).n1_detected && <Badge variant="warning">N+1</Badge>}
          <Badge variant="secondary">{qData(item).query_type}</Badge>
        </div>
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

  const detail = selected ? qData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold flex-1">Queries</h1>
        <div className="flex items-center gap-2">
          <Switch
            checked={slowOnly}
            onCheckedChange={setSlowOnly}
            id="slow-only"
          />
          <label htmlFor="slow-only" className="text-xs text-muted-foreground cursor-pointer">
            Slow only
          </label>
        </div>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by SQL..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent onClose={() => setSelected(null)} className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>Query Detail</DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div>
                <p className="text-sm font-semibold mb-2">SQL</p>
                <div className="bg-muted rounded-lg p-4 overflow-x-auto">
                  <SqlHighlight sql={detail.sql} />
                </div>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground">Duration</p>
                  <DurationBadge ms={detail.duration_ms} />
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Connection</p>
                  <p className="text-sm">{detail.connection}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Type</p>
                  <Badge variant="secondary">{detail.query_type}</Badge>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Caller</p>
                  <p className="text-xs font-mono text-muted-foreground truncate">
                    {detail.caller ?? "N/A"}
                  </p>
                </div>
              </div>

              {detail.bindings && detail.bindings.length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">Bindings</p>
                    <div className="bg-muted rounded-lg p-3 font-mono text-xs">
                      {detail.bindings.map((b, i) => (
                        <div key={i}>
                          <span className="text-muted-foreground">{i}:</span>{" "}
                          <span className="text-blue-500 dark:text-blue-400">
                            {JSON.stringify(b)}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                </>
              )}

              <div className="flex gap-2">
                {detail.slow && <Badge variant="danger">SLOW</Badge>}
                {detail.n1_detected && (
                  <Badge variant="warning">N+1 Detected</Badge>
                )}
              </div>
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

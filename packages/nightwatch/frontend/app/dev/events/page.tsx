"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
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
import type { BaseEntry, EventData } from "@/lib/schemas";

export default function EventsPage() {
  const { data, isLoading } = useEntries("event");
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const evData = (entry: BaseEntry): EventData =>
    entry.data as unknown as EventData;

  const shortClass = (cls: string) => {
    const parts = cls.split("\\");
    return parts[parts.length - 1] ?? cls;
  };

  const columns: ColumnDef[] = [
    {
      key: "event_class",
      label: "Event",
      render: (item) => (
        <div>
          <span className="text-sm font-semibold">
            {shortClass(evData(item).event_class)}
          </span>
          <p className="text-xs text-muted-foreground font-mono truncate max-w-md">
            {evData(item).event_class}
          </p>
        </div>
      ),
    },
    {
      key: "listeners",
      label: "Listeners",
      width: "100px",
      render: (item) => (
        <Badge variant="secondary">{evData(item).listeners.length}</Badge>
      ),
    },
    {
      key: "broadcast",
      label: "Broadcast",
      width: "100px",
      render: (item) =>
        evData(item).broadcast ? (
          <Badge variant="info">Yes</Badge>
        ) : (
          <span className="text-xs text-muted-foreground">No</span>
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

  const detail = selected ? evData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Events</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by event class..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent onClose={() => setSelected(null)} className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>
              {detail && shortClass(detail.event_class)}
            </DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div>
                <p className="text-xs text-muted-foreground">Full Class</p>
                <p className="text-sm font-mono">{detail.event_class}</p>
              </div>

              <div className="flex gap-2">
                <Badge variant="secondary">
                  {detail.listeners.length} listener(s)
                </Badge>
                {detail.broadcast && (
                  <Badge variant="info">Broadcast</Badge>
                )}
              </div>

              {detail.listeners.length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">Listeners</p>
                    <div className="space-y-1">
                      {detail.listeners.map((listener, i) => (
                        <div
                          key={i}
                          className="text-xs font-mono bg-muted rounded px-3 py-1.5"
                        >
                          {listener}
                        </div>
                      ))}
                    </div>
                  </div>
                </>
              )}

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

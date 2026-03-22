"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
import DurationBadge from "@/components/duration-badge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import type { BaseEntry, CacheData } from "@/lib/schemas";

const operationVariants: Record<
  string,
  "success" | "danger" | "info" | "default"
> = {
  hit: "success",
  miss: "danger",
  write: "info",
  forget: "default",
};

export default function CachePage() {
  const { data, isLoading } = useEntries("cache");
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const cData = (entry: BaseEntry): CacheData =>
    entry.data as unknown as CacheData;

  const columns: ColumnDef[] = [
    {
      key: "operation",
      label: "Operation",
      width: "100px",
      render: (item) => (
        <Badge
          variant={operationVariants[cData(item).operation] ?? "default"}
        >
          {cData(item).operation.toUpperCase()}
        </Badge>
      ),
    },
    {
      key: "key",
      label: "Key",
      render: (item) => (
        <span className="font-mono text-sm truncate max-w-md block">
          {cData(item).key}
        </span>
      ),
    },
    {
      key: "store",
      label: "Store",
      width: "100px",
      render: (item) => (
        <span className="text-xs text-muted-foreground">
          {cData(item).store}
        </span>
      ),
    },
    {
      key: "duration",
      label: "Duration",
      width: "90px",
      render: (item) => <DurationBadge ms={cData(item).duration_ms} />,
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

  const detail = selected ? cData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Cache</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by key..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent onClose={() => setSelected(null)}>
          <DialogHeader>
            <DialogTitle className="flex gap-3 items-center">
              Cache Operation
              {detail && (
                <Badge
                  variant={
                    operationVariants[detail.operation] ?? "default"
                  }
                >
                  {detail.operation.toUpperCase()}
                </Badge>
              )}
            </DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-xs text-muted-foreground">Key</p>
                <p className="text-sm font-mono break-all">{detail.key}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Store</p>
                <p className="text-sm">{detail.store}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Duration</p>
                <DurationBadge ms={detail.duration_ms} />
              </div>
              <div>
                <p className="text-xs text-muted-foreground">TTL</p>
                <p className="text-sm">
                  {detail.ttl != null ? `${detail.ttl}s` : "N/A"}
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Value Size</p>
                <p className="text-sm">
                  {detail.value_size != null
                    ? `${detail.value_size} bytes`
                    : "N/A"}
                </p>
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

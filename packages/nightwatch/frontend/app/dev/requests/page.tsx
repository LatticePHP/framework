"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
import StatusCode from "@/components/status-code";
import DurationBadge from "@/components/duration-badge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import type { BaseEntry, RequestData } from "@/lib/schemas";
import { useFiltersStore } from "@/lib/store";

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-sm font-mono truncate">{value}</p>
    </div>
  );
}

export default function RequestsPage() {
  const { data, isLoading } = useEntries("request");
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const { methodFilter, setMethodFilter, statusFilter, setStatusFilter } =
    useFiltersStore();

  const reqData = (entry: BaseEntry): RequestData =>
    entry.data as unknown as RequestData;

  const columns: ColumnDef[] = [
    {
      key: "method",
      label: "Method",
      width: "80px",
      render: (item) => {
        const method = reqData(item).method;
        const variant =
          method === "GET"
            ? "info"
            : method === "POST"
              ? "success"
              : method === "DELETE"
                ? "danger"
                : "warning";
        return <Badge variant={variant}>{method}</Badge>;
      },
    },
    {
      key: "uri",
      label: "URI",
      render: (item) => (
        <span className="font-mono text-sm truncate max-w-md block">
          {reqData(item).uri}
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      width: "80px",
      render: (item) => <StatusCode status={reqData(item).status} />,
    },
    {
      key: "duration",
      label: "Duration",
      width: "100px",
      render: (item) => <DurationBadge ms={reqData(item).duration_ms} />,
    },
    {
      key: "timestamp",
      label: "Time",
      width: "140px",
      render: (item) => (
        <span className="text-xs text-muted-foreground">
          {new Date(item.timestamp).toLocaleTimeString()}
        </span>
      ),
    },
  ];

  const detail = selected ? reqData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold flex-1">Requests</h1>
        <Select
          value={methodFilter ?? ""}
          onValueChange={(v) => setMethodFilter(v || null)}
          className="w-[120px] h-9 text-sm"
        >
          <option value="">All Methods</option>
          {["GET", "POST", "PUT", "PATCH", "DELETE"].map((m) => (
            <option key={m} value={m}>
              {m}
            </option>
          ))}
        </Select>
        <Select
          value={statusFilter ? String(statusFilter) : ""}
          onValueChange={(v) => setStatusFilter(v ? Number(v) : null)}
          className="w-[120px] h-9 text-sm"
        >
          <option value="">All Status</option>
          {["200", "201", "301", "302", "400", "401", "403", "404", "422", "500", "503"].map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </Select>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by URI..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent onClose={() => setSelected(null)} className="max-w-3xl">
          <DialogHeader>
            <DialogTitle className="flex gap-3 items-center">
              {detail && (
                <>
                  <Badge variant="info">{detail.method}</Badge>
                  <span className="font-mono text-sm">{detail.uri}</span>
                  <StatusCode status={detail.status} />
                </>
              )}
            </DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <InfoItem label="Duration" value={`${detail.duration_ms}ms`} />
                <InfoItem label="Status" value={String(detail.status)} />
                <InfoItem label="IP" value={detail.ip ?? "N/A"} />
                <InfoItem label="Controller" value={detail.controller ?? "N/A"} />
                <InfoItem label="Route" value={detail.route_name ?? "N/A"} />
                <InfoItem
                  label="Response Size"
                  value={
                    detail.response_size
                      ? `${detail.response_size} bytes`
                      : "N/A"
                  }
                />
                <InfoItem label="Content Type" value={detail.content_type ?? "N/A"} />
                <InfoItem
                  label="User ID"
                  value={
                    detail.user_id != null ? String(detail.user_id) : "Guest"
                  }
                />
              </div>

              {detail.middleware && detail.middleware.length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">Middleware</p>
                    <div className="flex flex-wrap gap-1">
                      {detail.middleware.map((m, i) => (
                        <Badge key={i} variant="secondary">{m}</Badge>
                      ))}
                    </div>
                  </div>
                </>
              )}

              {detail.headers && Object.keys(detail.headers).length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">Headers</p>
                    <div className="font-mono text-xs space-y-1 bg-muted rounded-lg p-3 max-h-60 overflow-auto">
                      {Object.entries(detail.headers).map(([key, val]) => (
                        <div key={key}>
                          <span className="text-blue-500 dark:text-blue-400">
                            {key}:
                          </span>{" "}
                          <span className="text-muted-foreground">
                            {String(val)}
                          </span>
                        </div>
                      ))}
                    </div>
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

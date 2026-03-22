"use client";

import { useState } from "react";
import { useEntries } from "@/lib/hooks";
import EntryTable from "@/components/entry-table";
import type { ColumnDef } from "@/components/entry-table";
import StackTrace from "@/components/stack-trace";
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
import type { BaseEntry, ExceptionData, StackFrame } from "@/lib/schemas";

export default function ExceptionsPage() {
  const { data, isLoading } = useEntries("exception");
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const exData = (entry: BaseEntry): ExceptionData =>
    entry.data as unknown as ExceptionData;

  const shortClass = (cls: string) => {
    const parts = cls.split("\\");
    return parts[parts.length - 1] ?? cls;
  };

  const columns: ColumnDef[] = [
    {
      key: "class",
      label: "Exception",
      render: (item) => (
        <div>
          <span className="text-sm font-semibold text-red-500 dark:text-red-400">
            {shortClass(exData(item).class)}
          </span>
          <p className="text-xs text-muted-foreground font-mono truncate max-w-lg">
            {exData(item).class}
          </p>
        </div>
      ),
    },
    {
      key: "message",
      label: "Message",
      render: (item) => (
        <span className="text-sm truncate max-w-sm block">
          {exData(item).message}
        </span>
      ),
    },
    {
      key: "location",
      label: "Location",
      width: "200px",
      render: (item) => (
        <span className="text-xs font-mono text-muted-foreground truncate block">
          {exData(item).file}:{exData(item).line}
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

  const detail = selected ? exData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Exceptions</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by class or message..."
      />

      <Dialog open={!!selected} onOpenChange={() => setSelected(null)}>
        <DialogContent
          onClose={() => setSelected(null)}
          className="max-w-4xl max-h-[80vh] overflow-y-auto"
        >
          <DialogHeader>
            <DialogTitle className="flex flex-col items-start gap-1">
              {detail && (
                <>
                  <Badge variant="danger">{shortClass(detail.class)}</Badge>
                  <p className="text-sm font-mono text-muted-foreground">
                    {detail.class}
                  </p>
                </>
              )}
            </DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div>
                <p className="text-sm font-semibold mb-1">Message</p>
                <p className="text-sm bg-red-50 dark:bg-red-950/30 rounded-lg p-3 text-red-600 dark:text-red-400">
                  {detail.message}
                </p>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground">Code</p>
                  <p className="text-sm">{detail.code ?? "N/A"}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">File</p>
                  <p className="text-xs font-mono truncate">{detail.file}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Line</p>
                  <p className="text-sm">{detail.line}</p>
                </div>
              </div>

              {detail.trace && detail.trace.length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">Stack Trace</p>
                    <div className="bg-muted rounded-lg p-3 overflow-x-auto">
                      <StackTrace frames={detail.trace as StackFrame[]} />
                    </div>
                  </div>
                </>
              )}

              {detail.previous && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">
                      Previous Exception
                    </p>
                    <div className="bg-muted rounded-lg p-3 text-xs font-mono">
                      <p className="text-red-500 dark:text-red-400">
                        {String(
                          (detail.previous as Record<string, unknown>).class,
                        )}
                      </p>
                      <p className="text-muted-foreground mt-1">
                        {String(
                          (detail.previous as Record<string, unknown>).message,
                        )}
                      </p>
                    </div>
                  </div>
                </>
              )}

              {detail.request_context &&
                Object.keys(detail.request_context).length > 0 && (
                  <>
                    <Separator />
                    <div>
                      <p className="text-sm font-semibold mb-2">
                        Request Context
                      </p>
                      <pre className="bg-muted rounded-lg p-3 text-xs font-mono overflow-x-auto">
                        {JSON.stringify(detail.request_context, null, 2)}
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

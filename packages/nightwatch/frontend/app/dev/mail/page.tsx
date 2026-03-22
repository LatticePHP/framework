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
import type { BaseEntry, MailData } from "@/lib/schemas";

export default function MailPage() {
  const { data, isLoading } = useEntries("mail");
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const [showPreview, setShowPreview] = useState(false);

  const mData = (entry: BaseEntry): MailData =>
    entry.data as unknown as MailData;

  const formatRecipients = (to: string | string[] | undefined): string => {
    if (!to) return "N/A";
    if (Array.isArray(to)) return to.join(", ");
    return to;
  };

  const columns: ColumnDef[] = [
    {
      key: "to",
      label: "To",
      render: (item) => (
        <span className="text-sm truncate max-w-xs block">
          {formatRecipients(mData(item).to)}
        </span>
      ),
    },
    {
      key: "subject",
      label: "Subject",
      render: (item) => (
        <span className="text-sm font-semibold truncate max-w-md block">
          {mData(item).subject}
        </span>
      ),
    },
    {
      key: "mailable",
      label: "Mailable",
      width: "160px",
      render: (item) => {
        const cls = mData(item).mailable_class;
        if (!cls)
          return (
            <span className="text-xs text-muted-foreground">N/A</span>
          );
        const parts = cls.split("\\");
        return (
          <span className="text-xs font-mono text-muted-foreground">
            {parts[parts.length - 1]}
          </span>
        );
      },
    },
    {
      key: "queued",
      label: "Queued",
      width: "80px",
      render: (item) =>
        mData(item).queued ? (
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

  const detail = selected ? mData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Mail</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by subject or recipient..."
      />

      <Dialog
        open={!!selected}
        onOpenChange={() => {
          setSelected(null);
          setShowPreview(false);
        }}
      >
        <DialogContent
          onClose={() => {
            setSelected(null);
            setShowPreview(false);
          }}
          className="max-w-3xl"
        >
          <DialogHeader>
            <DialogTitle>{detail?.subject ?? "Mail Detail"}</DialogTitle>
          </DialogHeader>
          {detail && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground">To</p>
                  <p className="text-sm">{formatRecipients(detail.to)}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">From</p>
                  <p className="text-sm">{detail.from ?? "N/A"}</p>
                </div>
                {detail.cc && (
                  <div>
                    <p className="text-xs text-muted-foreground">CC</p>
                    <p className="text-sm">{detail.cc.join(", ")}</p>
                  </div>
                )}
                {detail.bcc && (
                  <div>
                    <p className="text-xs text-muted-foreground">BCC</p>
                    <p className="text-sm">{detail.bcc.join(", ")}</p>
                  </div>
                )}
                <div>
                  <p className="text-xs text-muted-foreground">Mailable</p>
                  <p className="text-xs font-mono">
                    {detail.mailable_class ?? "N/A"}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Queued</p>
                  <p className="text-sm">{detail.queued ? "Yes" : "No"}</p>
                </div>
              </div>

              {detail.attachments && detail.attachments.length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-sm font-semibold mb-2">Attachments</p>
                    <div className="flex flex-wrap gap-1">
                      {detail.attachments.map((a, i) => (
                        <Badge key={i} variant="secondary">
                          {a}
                        </Badge>
                      ))}
                    </div>
                  </div>
                </>
              )}

              <Separator />
              <div>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-semibold">HTML Preview</p>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowPreview(!showPreview)}
                  >
                    {showPreview ? "Hide" : "Show"} Preview
                  </Button>
                </div>
                {showPreview && selected && (
                  <div className="border rounded-lg overflow-hidden">
                    <iframe
                      src={`/nightwatch/api/mail/${selected.uuid}/html`}
                      title="Mail preview"
                      className="w-full h-96 bg-white"
                      sandbox="allow-same-origin"
                    />
                  </div>
                )}
              </div>
            </div>
          )}
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setSelected(null);
                setShowPreview(false);
              }}
            >
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

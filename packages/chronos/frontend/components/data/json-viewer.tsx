"use client";

import { useState } from "react";
import { Copy, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";

interface JsonViewerProps {
  data: unknown;
  maxHeight?: string;
  className?: string;
}

export function JsonViewer({ data, maxHeight = "24rem", className }: JsonViewerProps) {
  const [copied, setCopied] = useState(false);
  const formatted = data != null ? JSON.stringify(data, null, 2) : "null";

  const handleCopy = async () => {
    await navigator.clipboard.writeText(formatted);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className={cn("relative rounded-md border bg-muted", className)}>
      <Button
        variant="ghost"
        size="icon"
        className="absolute right-2 top-2 h-7 w-7"
        onClick={() => void handleCopy()}
      >
        {copied ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
        <span className="sr-only">Copy</span>
      </Button>
      <ScrollArea style={{ maxHeight }}>
        <pre className="p-4 text-xs font-mono whitespace-pre-wrap break-all">{formatted}</pre>
      </ScrollArea>
    </div>
  );
}

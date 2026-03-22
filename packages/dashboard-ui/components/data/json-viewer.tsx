"use client"

import * as React from "react"
import { ChevronRightIcon } from "lucide-react"
import { ScrollArea } from "@/components/ui/scroll-area"
import { cn } from "@/lib/utils"

export type JsonViewerProps = {
  data: unknown
  /** Max initial depth to auto-expand (default: 2) */
  defaultExpandDepth?: number
  className?: string
}

export function JsonViewer({
  data,
  defaultExpandDepth = 2,
  className,
}: JsonViewerProps) {
  return (
    <ScrollArea className={cn("max-h-96 rounded-lg border bg-muted/30 p-3", className)}>
      <div className="font-mono text-xs">
        <JsonNode value={data} depth={0} defaultExpandDepth={defaultExpandDepth} />
      </div>
    </ScrollArea>
  )
}

function JsonNode({
  value,
  depth,
  defaultExpandDepth,
  keyName,
}: {
  value: unknown
  depth: number
  defaultExpandDepth: number
  keyName?: string
}) {
  const [expanded, setExpanded] = React.useState(depth < defaultExpandDepth)

  if (value === null) {
    return (
      <span className="inline">
        {keyName !== undefined && <JsonKey name={keyName} />}
        <span className="text-muted-foreground font-medium">null</span>
      </span>
    )
  }

  if (value === undefined) {
    return (
      <span className="inline">
        {keyName !== undefined && <JsonKey name={keyName} />}
        <span className="text-muted-foreground font-medium">undefined</span>
      </span>
    )
  }

  if (typeof value === "boolean") {
    return (
      <span className="inline">
        {keyName !== undefined && <JsonKey name={keyName} />}
        <span className="text-primary font-medium">
          {value ? "true" : "false"}
        </span>
      </span>
    )
  }

  if (typeof value === "number") {
    return (
      <span className="inline">
        {keyName !== undefined && <JsonKey name={keyName} />}
        <span className="text-primary font-medium">{value}</span>
      </span>
    )
  }

  if (typeof value === "string") {
    return (
      <span className="inline">
        {keyName !== undefined && <JsonKey name={keyName} />}
        <span className="text-accent-foreground">
          &quot;{value}&quot;
        </span>
      </span>
    )
  }

  if (Array.isArray(value)) {
    if (value.length === 0) {
      return (
        <span className="inline">
          {keyName !== undefined && <JsonKey name={keyName} />}
          <span className="text-muted-foreground">[]</span>
        </span>
      )
    }

    return (
      <div>
        <button
          type="button"
          className="inline-flex items-center gap-0.5 hover:text-foreground"
          onClick={() => setExpanded(!expanded)}
        >
          <ChevronRightIcon
            className={cn(
              "size-3 text-muted-foreground transition-transform",
              expanded && "rotate-90"
            )}
          />
          {keyName !== undefined && <JsonKey name={keyName} />}
          <span className="text-muted-foreground">
            [{!expanded && `${value.length} items`}
          </span>
        </button>
        {expanded && (
          <div className="ml-4 border-l border-border pl-2">
            {value.map((item, index) => (
              <div key={index}>
                <JsonNode
                  value={item}
                  depth={depth + 1}
                  defaultExpandDepth={defaultExpandDepth}
                  keyName={String(index)}
                />
                {index < value.length - 1 && (
                  <span className="text-muted-foreground">,</span>
                )}
              </div>
            ))}
          </div>
        )}
        <span className="text-muted-foreground">]</span>
      </div>
    )
  }

  if (typeof value === "object") {
    const entries = Object.entries(value as Record<string, unknown>)
    if (entries.length === 0) {
      return (
        <span className="inline">
          {keyName !== undefined && <JsonKey name={keyName} />}
          <span className="text-muted-foreground">{"{}"}</span>
        </span>
      )
    }

    return (
      <div>
        <button
          type="button"
          className="inline-flex items-center gap-0.5 hover:text-foreground"
          onClick={() => setExpanded(!expanded)}
        >
          <ChevronRightIcon
            className={cn(
              "size-3 text-muted-foreground transition-transform",
              expanded && "rotate-90"
            )}
          />
          {keyName !== undefined && <JsonKey name={keyName} />}
          <span className="text-muted-foreground">
            {"{"}{!expanded && `${entries.length} keys`}
          </span>
        </button>
        {expanded && (
          <div className="ml-4 border-l border-border pl-2">
            {entries.map(([key, val], index) => (
              <div key={key}>
                <JsonNode
                  value={val}
                  depth={depth + 1}
                  defaultExpandDepth={defaultExpandDepth}
                  keyName={key}
                />
                {index < entries.length - 1 && (
                  <span className="text-muted-foreground">,</span>
                )}
              </div>
            ))}
          </div>
        )}
        <span className="text-muted-foreground">{"}"}</span>
      </div>
    )
  }

  return (
    <span className="text-muted-foreground">{String(value)}</span>
  )
}

function JsonKey({ name }: { name: string }) {
  return (
    <span className="text-foreground font-medium">
      {name}
      <span className="text-muted-foreground">: </span>
    </span>
  )
}

"use client"

import * as React from "react"
import { ChevronRightIcon, FileIcon } from "lucide-react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { cn } from "@/lib/utils"

export type StackFrame = {
  file: string
  line: number
  function?: string
  class?: string
  isVendor?: boolean
  code?: {
    lines: { number: number; content: string }[]
    highlightLine?: number
  }
}

export type StackTraceProps = {
  frames: StackFrame[]
  className?: string
  /** Show vendor frames by default (default: false) */
  showVendorFrames?: boolean
}

export function StackTrace({
  frames,
  className,
  showVendorFrames = false,
}: StackTraceProps) {
  const [showVendor, setShowVendor] = React.useState(showVendorFrames)
  const vendorCount = frames.filter((f) => f.isVendor).length
  const visibleFrames = showVendor
    ? frames
    : frames.filter((f) => !f.isVendor)

  return (
    <Card className={cn(className)}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>Stack Trace</CardTitle>
          {vendorCount > 0 && (
            <button
              type="button"
              className="text-xs text-muted-foreground hover:text-foreground"
              onClick={() => setShowVendor(!showVendor)}
            >
              {showVendor ? "Hide" : "Show"} {vendorCount} vendor frame
              {vendorCount !== 1 ? "s" : ""}
            </button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-0">
          {visibleFrames.map((frame, index) => (
            <React.Fragment key={index}>
              {index > 0 && <Separator />}
              <StackFrameItem frame={frame} index={index} />
            </React.Fragment>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

function StackFrameItem({
  frame,
  index,
}: {
  frame: StackFrame
  index: number
}) {
  const [expanded, setExpanded] = React.useState(index === 0)
  const hasCode = frame.code && frame.code.lines.length > 0

  return (
    <div
      className={cn(
        "py-2",
        frame.isVendor && "opacity-50"
      )}
    >
      <button
        type="button"
        className="flex w-full items-start gap-2 text-left text-sm hover:text-foreground"
        onClick={() => hasCode && setExpanded(!expanded)}
        disabled={!hasCode}
      >
        {hasCode && (
          <ChevronRightIcon
            className={cn(
              "mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform",
              expanded && "rotate-90"
            )}
          />
        )}
        {!hasCode && <FileIcon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />}
        <div className="flex flex-1 flex-wrap items-center gap-1.5">
          {frame.class && (
            <span className="font-medium text-foreground">{frame.class}</span>
          )}
          {frame.class && frame.function && (
            <span className="text-muted-foreground">::</span>
          )}
          {frame.function && (
            <span className="font-medium text-foreground">
              {frame.function}()
            </span>
          )}
          <span className="text-muted-foreground">
            {frame.file}:{frame.line}
          </span>
          {frame.isVendor && (
            <Badge variant="secondary">vendor</Badge>
          )}
        </div>
      </button>
      {expanded && hasCode && (
        <div className="mt-2 ml-6 overflow-hidden rounded-md border bg-muted/30">
          <pre className="overflow-x-auto text-xs">
            <code>
              {frame.code!.lines.map((line) => (
                <div
                  key={line.number}
                  className={cn(
                    "flex px-3 py-0.5",
                    line.number === frame.code!.highlightLine &&
                      "bg-destructive/10 text-destructive"
                  )}
                >
                  <span className="mr-4 inline-block w-8 shrink-0 text-right text-muted-foreground select-none">
                    {line.number}
                  </span>
                  <span className="flex-1 whitespace-pre">{line.content}</span>
                </div>
              ))}
            </code>
          </pre>
        </div>
      )}
    </div>
  )
}

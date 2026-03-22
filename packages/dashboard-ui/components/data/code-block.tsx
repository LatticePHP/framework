"use client"

import * as React from "react"
import { CopyIcon, CheckIcon } from "lucide-react"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Button } from "@/components/ui/button"
import { useCopyToClipboard } from "@/hooks/use-copy-to-clipboard"
import { cn } from "@/lib/utils"

export type CodeBlockProps = {
  code: string
  language?: string
  /** Show line numbers (default: true) */
  showLineNumbers?: boolean
  /** Starting line number (default: 1) */
  startLine?: number
  /** Line numbers to highlight */
  highlightLines?: number[]
  /** Show copy button (default: true) */
  showCopy?: boolean
  className?: string
  /** Max height before scrolling */
  maxHeight?: string
}

export function CodeBlock({
  code,
  language,
  showLineNumbers = true,
  startLine = 1,
  highlightLines = [],
  showCopy = true,
  className,
  maxHeight = "24rem",
}: CodeBlockProps) {
  const { copy, copied } = useCopyToClipboard()
  const lines = code.split("\n")
  const highlightSet = new Set(highlightLines)

  return (
    <div className={cn("relative rounded-lg border bg-muted/30", className)}>
      {(language || showCopy) && (
        <div className="flex items-center justify-between border-b px-3 py-1.5">
          {language && (
            <span className="text-xs font-medium text-muted-foreground">
              {language}
            </span>
          )}
          {showCopy && (
            <Button
              variant="ghost"
              size="icon-xs"
              onClick={() => copy(code)}
            >
              {copied ? (
                <CheckIcon className="size-3" />
              ) : (
                <CopyIcon className="size-3" />
              )}
              <span className="sr-only">Copy code</span>
            </Button>
          )}
        </div>
      )}
      <ScrollArea style={{ maxHeight }}>
        <pre className="overflow-x-auto p-3 text-xs">
          <code className="font-mono">
            {lines.map((line, index) => {
              const lineNumber = startLine + index
              return (
                <div
                  key={index}
                  className={cn(
                    "flex",
                    highlightSet.has(lineNumber) &&
                      "bg-destructive/10 text-destructive"
                  )}
                >
                  {showLineNumbers && (
                    <span className="mr-4 inline-block w-8 shrink-0 text-right text-muted-foreground select-none">
                      {lineNumber}
                    </span>
                  )}
                  <span className="flex-1 whitespace-pre">{line}</span>
                </div>
              )
            })}
          </code>
        </pre>
      </ScrollArea>
    </div>
  )
}

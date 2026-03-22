"use client"

import * as React from "react"
import { RefreshCwIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  type RefreshInterval,
  REFRESH_INTERVALS,
} from "@/hooks/use-auto-refresh"
import { cn } from "@/lib/utils"

export type AutoRefreshProps = {
  interval: RefreshInterval
  onIntervalChange: (interval: RefreshInterval) => void
  className?: string
}

export function AutoRefresh({
  interval,
  onIntervalChange,
  className,
}: AutoRefreshProps) {
  const isActive = interval > 0
  const activeLabel = REFRESH_INTERVALS.find(
    (item) => item.value === interval
  )?.label

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button variant="outline" size="sm" className={cn(className)} />
        }
      >
        <span className="relative flex items-center gap-1.5">
          {isActive && (
            <span className="relative flex size-2">
              <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
              <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
            </span>
          )}
          <RefreshCwIcon className="size-3.5" />
          <span>{activeLabel ?? "Off"}</span>
        </span>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {REFRESH_INTERVALS.map((item) => (
          <DropdownMenuItem
            key={item.value}
            onClick={() => onIntervalChange(item.value)}
          >
            <span
              className={cn(
                "flex-1",
                item.value === interval && "font-medium"
              )}
            >
              {item.label}
            </span>
            {item.value === interval && (
              <span className="text-xs text-muted-foreground">Active</span>
            )}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

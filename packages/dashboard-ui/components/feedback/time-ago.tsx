"use client"

import * as React from "react"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { formatRelativeTime, formatDateTime } from "@/lib/formatters"
import { cn } from "@/lib/utils"

export type TimeAgoProps = {
  date: Date | string | number
  /** Update interval in ms (default: 30000) */
  updateInterval?: number
  className?: string
}

export function TimeAgo({
  date,
  updateInterval = 30000,
  className,
}: TimeAgoProps) {
  const d = React.useMemo(
    () => (date instanceof Date ? date : new Date(date)),
    [date]
  )
  const [relative, setRelative] = React.useState(() => formatRelativeTime(d))

  React.useEffect(() => {
    setRelative(formatRelativeTime(d))
    const timer = setInterval(() => {
      setRelative(formatRelativeTime(d))
    }, updateInterval)
    return () => clearInterval(timer)
  }, [d, updateInterval])

  const fullDateTime = React.useMemo(() => formatDateTime(d), [d])

  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <span
            className={cn(
              "cursor-default text-sm text-muted-foreground",
              className
            )}
          />
        }
      >
        {relative}
      </TooltipTrigger>
      <TooltipContent>{fullDateTime}</TooltipContent>
    </Tooltip>
  )
}

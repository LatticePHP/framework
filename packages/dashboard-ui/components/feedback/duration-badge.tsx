"use client"

import { Badge } from "@/components/ui/badge"
import { formatDuration } from "@/lib/formatters"
import { cn } from "@/lib/utils"

export type DurationBadgeProps = {
  /** Duration in milliseconds */
  ms: number
  /** Threshold for slow (default: 1000ms) */
  slowThreshold?: number
  /** Threshold for very slow (default: 5000ms) */
  verySlowThreshold?: number
  className?: string
}

export function DurationBadge({
  ms,
  slowThreshold = 1000,
  verySlowThreshold = 5000,
  className,
}: DurationBadgeProps) {
  const variant =
    ms >= verySlowThreshold
      ? "destructive"
      : ms >= slowThreshold
        ? "outline"
        : "secondary"

  return (
    <Badge variant={variant} className={cn("font-mono", className)}>
      {formatDuration(ms)}
    </Badge>
  )
}

"use client"

import * as React from "react"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

export type TimeRange = "1h" | "6h" | "24h" | "7d" | "30d"

export type TimeRangePickerProps = {
  value: TimeRange
  onChange: (value: TimeRange) => void
  className?: string
}

const ranges: { label: string; value: TimeRange }[] = [
  { label: "1h", value: "1h" },
  { label: "6h", value: "6h" },
  { label: "24h", value: "24h" },
  { label: "7d", value: "7d" },
  { label: "30d", value: "30d" },
]

export function TimeRangePicker({
  value,
  onChange,
  className,
}: TimeRangePickerProps) {
  return (
    <div className={cn("flex items-center gap-1", className)}>
      {ranges.map((range) => (
        <Button
          key={range.value}
          variant={value === range.value ? "secondary" : "ghost"}
          size="xs"
          onClick={() => onChange(range.value)}
        >
          {range.label}
        </Button>
      ))}
    </div>
  )
}

/**
 * Convert a TimeRange value to milliseconds.
 */
export function timeRangeToMs(range: TimeRange): number {
  const map: Record<TimeRange, number> = {
    "1h": 3600000,
    "6h": 21600000,
    "24h": 86400000,
    "7d": 604800000,
    "30d": 2592000000,
  }
  return map[range]
}

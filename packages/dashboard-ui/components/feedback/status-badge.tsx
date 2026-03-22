"use client"

import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

type BadgeVariant =
  | "default"
  | "secondary"
  | "destructive"
  | "outline"

export type StatusBadgeProps = {
  status: string
  /** Map status values to badge variants */
  colorMap?: Record<string, BadgeVariant>
  className?: string
}

const defaultColorMap: Record<string, BadgeVariant> = {
  success: "default",
  active: "default",
  running: "default",
  completed: "default",
  healthy: "default",
  pending: "secondary",
  queued: "secondary",
  idle: "secondary",
  warning: "outline",
  degraded: "outline",
  error: "destructive",
  failed: "destructive",
  critical: "destructive",
  down: "destructive",
  inactive: "outline",
}

export function StatusBadge({
  status,
  colorMap = defaultColorMap,
  className,
}: StatusBadgeProps) {
  const variant = colorMap[status.toLowerCase()] ?? "secondary"

  return (
    <Badge variant={variant} className={cn(className)}>
      {status}
    </Badge>
  )
}

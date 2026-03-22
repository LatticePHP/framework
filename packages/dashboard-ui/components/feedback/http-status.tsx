"use client"

import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

export type HttpStatusProps = {
  status: number
  className?: string
}

function getVariant(status: number) {
  if (status >= 500) return "destructive" as const
  if (status >= 400) return "outline" as const
  if (status >= 300) return "secondary" as const
  if (status >= 200) return "default" as const
  return "secondary" as const
}

export function HttpStatus({ status, className }: HttpStatusProps) {
  const variant = getVariant(status)

  return (
    <Badge variant={variant} className={cn("font-mono", className)}>
      {status}
    </Badge>
  )
}

"use client"

import * as React from "react"
import { TrendingUpIcon, TrendingDownIcon, MinusIcon } from "lucide-react"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from "@/components/ui/card"
import { cn } from "@/lib/utils"

export type StatCardProps = {
  title: string
  value: string | number
  description?: string
  trend?: "up" | "down" | "neutral"
  icon?: React.ComponentType<{ className?: string }>
  className?: string
}

const trendConfig = {
  up: {
    icon: TrendingUpIcon,
    className: "text-emerald-600 dark:text-emerald-400",
  },
  down: {
    icon: TrendingDownIcon,
    className: "text-destructive",
  },
  neutral: {
    icon: MinusIcon,
    className: "text-muted-foreground",
  },
} as const

export function StatCard({
  title,
  value,
  description,
  trend,
  icon: Icon,
  className,
}: StatCardProps) {
  const trendInfo = trend ? trendConfig[trend] : null

  return (
    <Card className={cn(className)}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-medium text-muted-foreground">
            {title}
          </CardTitle>
          {Icon && <Icon className="size-4 text-muted-foreground" />}
        </div>
        <div className="flex items-center gap-2">
          <span className="text-2xl font-bold tracking-tight">{value}</span>
          {trendInfo && (
            <trendInfo.icon className={cn("size-4", trendInfo.className)} />
          )}
        </div>
      </CardHeader>
      {description && (
        <CardContent>
          <CardDescription>{description}</CardDescription>
        </CardContent>
      )}
    </Card>
  )
}

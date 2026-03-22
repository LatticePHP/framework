"use client"

import * as React from "react"
import {
  Area,
  AreaChart as RechartsAreaChart,
  CartesianGrid,
  XAxis,
  YAxis,
} from "recharts"
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { cn } from "@/lib/utils"

export type AreaChartProps = {
  data: Record<string, unknown>[]
  config: ChartConfig
  /** Key in data used for the X axis (default: "date") */
  xAxisKey?: string
  /** Data keys to render as area series */
  dataKeys: string[]
  /** Show grid lines (default: true) */
  showGrid?: boolean
  /** Show legend (default: false) */
  showLegend?: boolean
  /** Show Y axis (default: false) */
  showYAxis?: boolean
  /** Stack areas (default: false) */
  stacked?: boolean
  /** Custom X axis formatter */
  xAxisFormatter?: (value: string) => string
  className?: string
}

export function AreaChart({
  data,
  config,
  xAxisKey = "date",
  dataKeys,
  showGrid = true,
  showLegend = false,
  showYAxis = false,
  stacked = false,
  xAxisFormatter,
  className,
}: AreaChartProps) {
  return (
    <ChartContainer config={config} className={cn(className)}>
      <RechartsAreaChart data={data} margin={{ left: 12, right: 12 }}>
        {showGrid && (
          <CartesianGrid vertical={false} />
        )}
        <XAxis
          dataKey={xAxisKey}
          tickLine={false}
          axisLine={false}
          tickMargin={8}
          tickFormatter={xAxisFormatter}
        />
        {showYAxis && (
          <YAxis
            tickLine={false}
            axisLine={false}
            tickMargin={8}
          />
        )}
        <ChartTooltip
          content={<ChartTooltipContent indicator="dot" />}
        />
        {showLegend && (
          <ChartLegend content={<ChartLegendContent />} />
        )}
        {dataKeys.map((key) => (
          <Area
            key={key}
            dataKey={key}
            type="monotone"
            fill={`var(--color-${key})`}
            fillOpacity={0.2}
            stroke={`var(--color-${key})`}
            strokeWidth={2}
            stackId={stacked ? "stack" : undefined}
          />
        ))}
      </RechartsAreaChart>
    </ChartContainer>
  )
}

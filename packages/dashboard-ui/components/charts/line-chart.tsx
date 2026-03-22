"use client"

import * as React from "react"
import {
  Line,
  LineChart as RechartsLineChart,
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

export type LineChartProps = {
  data: Record<string, unknown>[]
  config: ChartConfig
  /** Key in data used for the X axis (default: "date") */
  xAxisKey?: string
  /** Data keys to render as line series */
  dataKeys: string[]
  /** Show grid lines (default: true) */
  showGrid?: boolean
  /** Show legend (default: false) */
  showLegend?: boolean
  /** Show Y axis (default: false) */
  showYAxis?: boolean
  /** Show dots on data points (default: false) */
  showDots?: boolean
  /** Custom X axis formatter */
  xAxisFormatter?: (value: string) => string
  className?: string
}

export function LineChart({
  data,
  config,
  xAxisKey = "date",
  dataKeys,
  showGrid = true,
  showLegend = false,
  showYAxis = false,
  showDots = false,
  xAxisFormatter,
  className,
}: LineChartProps) {
  return (
    <ChartContainer config={config} className={cn(className)}>
      <RechartsLineChart data={data} margin={{ left: 12, right: 12 }}>
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
          content={<ChartTooltipContent indicator="line" />}
        />
        {showLegend && (
          <ChartLegend content={<ChartLegendContent />} />
        )}
        {dataKeys.map((key) => (
          <Line
            key={key}
            dataKey={key}
            type="monotone"
            stroke={`var(--color-${key})`}
            strokeWidth={2}
            dot={showDots}
            activeDot={{ r: 4 }}
          />
        ))}
      </RechartsLineChart>
    </ChartContainer>
  )
}

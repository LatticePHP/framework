"use client"

import * as React from "react"
import {
  Bar,
  BarChart as RechartsBarChart,
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

export type BarChartProps = {
  data: Record<string, unknown>[]
  config: ChartConfig
  /** Key in data used for the X axis (default: "name") */
  xAxisKey?: string
  /** Data keys to render as bar series */
  dataKeys: string[]
  /** Show grid lines (default: true) */
  showGrid?: boolean
  /** Show legend (default: false) */
  showLegend?: boolean
  /** Show Y axis (default: false) */
  showYAxis?: boolean
  /** Stack bars (default: false) */
  stacked?: boolean
  /** Horizontal layout (default: false) */
  horizontal?: boolean
  /** Custom X axis formatter */
  xAxisFormatter?: (value: string) => string
  className?: string
}

export function BarChart({
  data,
  config,
  xAxisKey = "name",
  dataKeys,
  showGrid = true,
  showLegend = false,
  showYAxis = false,
  stacked = false,
  horizontal = false,
  xAxisFormatter,
  className,
}: BarChartProps) {
  return (
    <ChartContainer config={config} className={cn(className)}>
      <RechartsBarChart
        data={data}
        layout={horizontal ? "vertical" : "horizontal"}
        margin={{ left: 12, right: 12 }}
      >
        {showGrid && (
          <CartesianGrid vertical={!horizontal} horizontal={horizontal} />
        )}
        {horizontal ? (
          <>
            <YAxis
              dataKey={xAxisKey}
              type="category"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              tickFormatter={xAxisFormatter}
            />
            <XAxis type="number" hide />
          </>
        ) : (
          <>
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
          </>
        )}
        <ChartTooltip
          content={<ChartTooltipContent />}
        />
        {showLegend && (
          <ChartLegend content={<ChartLegendContent />} />
        )}
        {dataKeys.map((key) => (
          <Bar
            key={key}
            dataKey={key}
            fill={`var(--color-${key})`}
            radius={[4, 4, 0, 0]}
            stackId={stacked ? "stack" : undefined}
          />
        ))}
      </RechartsBarChart>
    </ChartContainer>
  )
}

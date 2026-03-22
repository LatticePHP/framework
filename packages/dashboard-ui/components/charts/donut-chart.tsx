"use client"

import * as React from "react"
import { Pie, PieChart as RechartsPieChart, Cell, Label } from "recharts"
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { cn } from "@/lib/utils"

export type DonutChartProps = {
  data: { name: string; value: number; fill?: string }[]
  config: ChartConfig
  /** Data key for the value (default: "value") */
  dataKey?: string
  /** Data key for the name (default: "name") */
  nameKey?: string
  /** Show legend (default: true) */
  showLegend?: boolean
  /** Inner radius for donut hole (default: 60) */
  innerRadius?: number
  /** Outer radius (default: 80) */
  outerRadius?: number
  /** Center label text */
  centerLabel?: string
  /** Center value text */
  centerValue?: string
  className?: string
}

export function DonutChart({
  data,
  config,
  dataKey = "value",
  nameKey = "name",
  showLegend = true,
  innerRadius = 60,
  outerRadius = 80,
  centerLabel,
  centerValue,
  className,
}: DonutChartProps) {
  return (
    <ChartContainer config={config} className={cn(className)}>
      <RechartsPieChart>
        <ChartTooltip
          content={<ChartTooltipContent nameKey={nameKey} hideLabel />}
        />
        <Pie
          data={data}
          dataKey={dataKey}
          nameKey={nameKey}
          innerRadius={innerRadius}
          outerRadius={outerRadius}
          strokeWidth={2}
          stroke="hsl(var(--background))"
        >
          {data.map((entry) => (
            <Cell
              key={entry.name}
              fill={entry.fill ?? `var(--color-${entry.name})`}
            />
          ))}
          {(centerLabel || centerValue) && (
            <Label
              content={({ viewBox }) => {
                if (viewBox && "cx" in viewBox && "cy" in viewBox) {
                  return (
                    <text
                      x={viewBox.cx}
                      y={viewBox.cy}
                      textAnchor="middle"
                      dominantBaseline="middle"
                    >
                      {centerValue && (
                        <tspan
                          x={viewBox.cx}
                          y={viewBox.cy}
                          className="fill-foreground text-2xl font-bold"
                        >
                          {centerValue}
                        </tspan>
                      )}
                      {centerLabel && (
                        <tspan
                          x={viewBox.cx}
                          y={(viewBox.cy ?? 0) + 20}
                          className="fill-muted-foreground text-xs"
                        >
                          {centerLabel}
                        </tspan>
                      )}
                    </text>
                  )
                }
                return null
              }}
            />
          )}
        </Pie>
        {showLegend && (
          <ChartLegend
            content={<ChartLegendContent nameKey={nameKey} />}
          />
        )}
      </RechartsPieChart>
    </ChartContainer>
  )
}

"use client";

import {
  ResponsiveContainer,
  LineChart as RechartsLineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from "recharts";
import { formatTimestamp } from "@/lib/utils";

interface LineChartProps {
  data: Record<string, unknown>[];
  dataKey: string;
  label: string;
  color?: string;
  yFormatter?: (value: number) => string;
  emptyMessage?: string;
}

export function LineChart({
  data,
  dataKey,
  label,
  color = "var(--color-primary)",
  yFormatter,
  emptyMessage = "No data for this period",
}: LineChartProps) {
  if (data.length === 0) {
    return (
      <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
        {emptyMessage}
      </div>
    );
  }

  return (
    <ResponsiveContainer width="100%" height={280}>
      <RechartsLineChart
        data={data}
        margin={{ top: 5, right: 20, left: 0, bottom: 5 }}
      >
        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
        <XAxis
          dataKey="timestamp"
          tickFormatter={formatTimestamp}
          fontSize={12}
          className="fill-muted-foreground"
        />
        <YAxis
          fontSize={12}
          className="fill-muted-foreground"
          tickFormatter={yFormatter}
          allowDecimals={false}
        />
        <Tooltip
          labelFormatter={(val) => formatTimestamp(val as number)}
          formatter={(value: number) => [
            yFormatter ? yFormatter(value) : value,
            label,
          ]}
          contentStyle={{
            backgroundColor: "var(--color-card)",
            borderColor: "var(--color-border)",
            borderRadius: "8px",
            color: "var(--color-foreground)",
          }}
        />
        <Line
          type="monotone"
          dataKey={dataKey}
          stroke={color}
          strokeWidth={2}
          dot={false}
          activeDot={{ r: 4 }}
        />
      </RechartsLineChart>
    </ResponsiveContainer>
  );
}

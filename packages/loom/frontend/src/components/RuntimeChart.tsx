import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from 'recharts';
import type { RuntimePoint } from '@/schemas/job';

interface RuntimeChartProps {
  data: RuntimePoint[];
}

function formatTime(timestamp: number): string {
  return new Date(timestamp * 1000).toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatMs(ms: number): string {
  if (ms >= 1000) {
    return `${(ms / 1000).toFixed(1)}s`;
  }
  return `${ms.toFixed(0)}ms`;
}

export default function RuntimeChart({ data }: RuntimeChartProps) {
  if (data.length === 0) {
    return (
      <div className="flex items-center justify-center h-64 text-default-400 text-sm">
        No runtime data for this period
      </div>
    );
  }

  return (
    <ResponsiveContainer width="100%" height={280}>
      <LineChart data={data} margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
        <XAxis
          dataKey="timestamp"
          tickFormatter={formatTime}
          fontSize={12}
          tick={{ fill: 'hsl(var(--nextui-default-500))' }}
        />
        <YAxis
          fontSize={12}
          tick={{ fill: 'hsl(var(--nextui-default-500))' }}
          tickFormatter={(val) => formatMs(val as number)}
        />
        <Tooltip
          labelFormatter={(val) => formatTime(val as number)}
          formatter={(value: number) => [formatMs(value), 'Avg Runtime']}
          contentStyle={{
            backgroundColor: 'hsl(var(--nextui-content1))',
            borderColor: 'hsl(var(--nextui-divider))',
            borderRadius: '8px',
          }}
        />
        <Line
          type="monotone"
          dataKey="avg_runtime_ms"
          stroke="hsl(var(--nextui-secondary))"
          strokeWidth={2}
          dot={false}
          activeDot={{ r: 4 }}
        />
      </LineChart>
    </ResponsiveContainer>
  );
}

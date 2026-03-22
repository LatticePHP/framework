import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Spinner,
  Chip,
} from '@nextui-org/react';
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from 'recharts';
import { useSlowRequests } from '@/api/metrics';
import DurationBadge from '@/components/DurationBadge';

export default function SlowRequestsPage() {
  const { data, isLoading, error } = useSlowRequests();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Spinner label="Loading slow requests..." size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12 text-danger">
        Failed to load slow requests data
      </div>
    );
  }

  const items = data?.data ?? [];

  // Chart data: top 10 by p95
  const chartData = [...items]
    .sort((a, b) => b.p95 - a.p95)
    .slice(0, 10)
    .map((r) => ({
      endpoint: r.endpoint.length > 30 ? r.endpoint.slice(0, 30) + '...' : r.endpoint,
      P50: Math.round(r.p50),
      P95: Math.round(r.p95),
      P99: Math.round(r.p99),
    }));

  return (
    <div>
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <h1 className="text-xl font-bold flex-1">Slow Requests</h1>
        {data && (
          <Chip size="sm" variant="flat">
            {data.total_requests.toLocaleString()} total requests
          </Chip>
        )}
      </div>

      {/* Chart */}
      {chartData.length > 0 && (
        <div className="bg-content1 rounded-xl p-4 mb-6 border border-divider">
          <p className="text-sm font-semibold mb-3">Response Time by Endpoint (ms)</p>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={chartData} layout="vertical" margin={{ left: 120 }}>
              <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
              <XAxis type="number" tick={{ fontSize: 12 }} />
              <YAxis type="category" dataKey="endpoint" tick={{ fontSize: 11 }} width={120} />
              <Tooltip
                contentStyle={{
                  backgroundColor: 'hsl(var(--nextui-content1))',
                  border: '1px solid hsl(var(--nextui-divider))',
                  borderRadius: '8px',
                  fontSize: 12,
                }}
              />
              <Bar dataKey="P50" fill="hsl(var(--nextui-success))" stackId="a" name="P50" />
              <Bar dataKey="P95" fill="hsl(var(--nextui-warning))" stackId="b" name="P95" />
              <Bar dataKey="P99" fill="hsl(var(--nextui-danger))" stackId="c" name="P99" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Table */}
      <Table
        aria-label="Slow requests"
        isStriped
        isHeaderSticky
        classNames={{
          wrapper: 'max-h-[calc(100vh-500px)] overflow-auto',
        }}
      >
        <TableHeader>
          <TableColumn>Endpoint</TableColumn>
          <TableColumn width={80}>Method</TableColumn>
          <TableColumn width={80}>Count</TableColumn>
          <TableColumn width={100}>AVG</TableColumn>
          <TableColumn width={100}>P50</TableColumn>
          <TableColumn width={100}>P95</TableColumn>
          <TableColumn width={100}>P99</TableColumn>
        </TableHeader>
        <TableBody
          items={items}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent="No slow requests data."
        >
          {(item) => (
            <TableRow key={item.endpoint}>
              <TableCell>
                <span className="font-mono text-sm">{item.endpoint}</span>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat">
                  {item.method ?? 'ALL'}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-sm">{item.count.toLocaleString()}</span>
              </TableCell>
              <TableCell>
                <DurationBadge ms={item.avg} />
              </TableCell>
              <TableCell>
                <DurationBadge ms={item.p50} />
              </TableCell>
              <TableCell>
                <DurationBadge ms={item.p95} />
              </TableCell>
              <TableCell>
                <DurationBadge ms={item.p99} />
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}

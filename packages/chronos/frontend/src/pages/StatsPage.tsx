import { Card, CardBody, CardHeader, Spinner, Divider } from '@nextui-org/react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Legend,
} from 'recharts';
import { useWorkflowStats } from '@/api/stats';

function formatDuration(ms: number): string {
  if (ms < 1000) return `${Math.round(ms)}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  if (ms < 3600000) return `${(ms / 60000).toFixed(1)}m`;
  return `${(ms / 3600000).toFixed(1)}h`;
}

export function StatsPage() {
  const { data: statsResponse, isLoading, isError, error } = useWorkflowStats();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Spinner size="lg" label="Loading statistics..." />
      </div>
    );
  }

  if (isError || !statsResponse) {
    return (
      <div className="bg-danger-50 dark:bg-danger-100/10 border border-danger-200 dark:border-danger-500/30 rounded-xl p-6">
        <h3 className="text-danger font-bold text-lg">Failed to Load Statistics</h3>
        <p className="text-default-500 mt-2">
          {error instanceof Error ? error.message : 'Unknown error'}
        </p>
      </div>
    );
  }

  const stats = statsResponse.data;

  const statusData = [
    { name: 'Running', value: stats.running, color: '#006FEE' },
    { name: 'Completed', value: stats.completed, color: '#17C964' },
    { name: 'Failed', value: stats.failed, color: '#F31260' },
    { name: 'Cancelled', value: stats.cancelled, color: '#F5A524' },
  ];

  const barData = [
    { name: 'Running', count: stats.running },
    { name: 'Completed', count: stats.completed },
    { name: 'Failed', count: stats.failed },
    { name: 'Cancelled', count: stats.cancelled },
  ];

  const total = stats.running + stats.completed + stats.failed + stats.cancelled;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold">Statistics</h2>
        <p className="text-sm text-default-400 mt-1">
          Aggregate workflow execution metrics
        </p>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <Card>
          <CardBody className="p-4">
            <p className="text-xs text-default-400 uppercase tracking-wider">
              Total
            </p>
            <p className="text-3xl font-bold mt-1">{total}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="p-4">
            <p className="text-xs text-default-400 uppercase tracking-wider">Running</p>
            <p className="text-3xl font-bold text-primary mt-1">{stats.running}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="p-4">
            <p className="text-xs text-default-400 uppercase tracking-wider">
              Completed
            </p>
            <p className="text-3xl font-bold text-success mt-1">{stats.completed}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="p-4">
            <p className="text-xs text-default-400 uppercase tracking-wider">Failed</p>
            <p className="text-3xl font-bold text-danger mt-1">{stats.failed}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="p-4">
            <p className="text-xs text-default-400 uppercase tracking-wider">
              Avg Duration
            </p>
            <p className="text-3xl font-bold mt-1">
              {formatDuration(stats.avg_duration_ms)}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Bar chart */}
        <Card>
          <CardHeader className="pb-0">
            <h3 className="text-lg font-semibold">Workflow Status Distribution</h3>
          </CardHeader>
          <Divider className="my-2" />
          <CardBody>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={barData}>
                  <XAxis
                    dataKey="name"
                    tick={{ fontSize: 12 }}
                    stroke="currentColor"
                    strokeOpacity={0.3}
                  />
                  <YAxis
                    tick={{ fontSize: 12 }}
                    stroke="currentColor"
                    strokeOpacity={0.3}
                    allowDecimals={false}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: 'var(--nextui-content1)',
                      border: '1px solid var(--nextui-divider)',
                      borderRadius: '8px',
                      fontSize: '12px',
                    }}
                  />
                  <Bar dataKey="count" radius={[4, 4, 0, 0]}>
                    {barData.map((_entry, index) => (
                      <Cell
                        key={`cell-${index}`}
                        fill={statusData[index]?.color ?? '#888'}
                      />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardBody>
        </Card>

        {/* Pie chart */}
        <Card>
          <CardHeader className="pb-0">
            <h3 className="text-lg font-semibold">Status Breakdown</h3>
          </CardHeader>
          <Divider className="my-2" />
          <CardBody>
            <div className="h-64">
              {total > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={statusData.filter((d) => d.value > 0)}
                      cx="50%"
                      cy="50%"
                      innerRadius={50}
                      outerRadius={80}
                      paddingAngle={4}
                      dataKey="value"
                    >
                      {statusData
                        .filter((d) => d.value > 0)
                        .map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                    </Pie>
                    <Tooltip
                      contentStyle={{
                        backgroundColor: 'var(--nextui-content1)',
                        border: '1px solid var(--nextui-divider)',
                        borderRadius: '8px',
                        fontSize: '12px',
                      }}
                    />
                    <Legend
                      formatter={(value: string) => (
                        <span className="text-xs">{value}</span>
                      )}
                    />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex items-center justify-center h-full text-default-400">
                  No data available
                </div>
              )}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Summary table */}
      <Card>
        <CardHeader className="pb-0">
          <h3 className="text-lg font-semibold">Summary</h3>
        </CardHeader>
        <Divider className="my-2" />
        <CardBody>
          <div className="space-y-3">
            {statusData.map((item) => (
              <div
                key={item.name}
                className="flex items-center justify-between py-2 border-b border-divider last:border-0"
              >
                <div className="flex items-center gap-3">
                  <div
                    className="w-3 h-3 rounded-full"
                    style={{ backgroundColor: item.color }}
                  />
                  <span className="text-sm">{item.name}</span>
                </div>
                <div className="flex items-center gap-4">
                  <span className="text-sm font-mono font-bold">{item.value}</span>
                  <span className="text-xs text-default-400 w-12 text-right">
                    {total > 0 ? `${((item.value / total) * 100).toFixed(1)}%` : '0%'}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

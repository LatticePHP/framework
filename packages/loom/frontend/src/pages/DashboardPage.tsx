import { Card, CardBody, CardHeader, Skeleton, Button, ButtonGroup } from '@nextui-org/react';
import { useDashboardStats } from '@/api/stats';
import { useQueueMetrics } from '@/api/metrics';
import { useFiltersStore } from '@/stores/filters';
import ThroughputChart from '@/components/ThroughputChart';
import RuntimeChart from '@/components/RuntimeChart';
import QueueSizeBar from '@/components/QueueSizeBar';

const periods = ['5m', '1h', '6h', '24h', '7d'] as const;

function formatMs(ms: number): string {
  if (ms >= 1000) return `${(ms / 1000).toFixed(1)}s`;
  return `${ms.toFixed(0)}ms`;
}

export default function DashboardPage() {
  const { data: stats, isLoading: statsLoading, error: statsError, refetch } = useDashboardStats();
  const { data: metrics, isLoading: metricsLoading } = useQueueMetrics();
  const period = useFiltersStore((s) => s.period);
  const setPeriod = useFiltersStore((s) => s.setPeriod);

  if (statsError) {
    return (
      <div className="flex flex-col items-center justify-center py-20 gap-4">
        <p className="text-danger text-sm">Failed to connect to Loom API</p>
        <Button color="primary" size="sm" onPress={() => void refetch()}>
          Retry
        </Button>
      </div>
    );
  }

  const metricCards = [
    { label: 'Processed', value: stats?.total_processed.toLocaleString(), sub: `${stats?.processed_last_hour ?? 0} last hour` },
    { label: 'Failed', value: stats?.total_failed.toLocaleString(), sub: `${stats?.failed_last_hour ?? 0} last hour`, danger: (stats?.total_failed ?? 0) > 0 },
    { label: 'Throughput', value: `${stats?.throughput_per_minute ?? 0}/min`, sub: 'jobs per minute' },
    { label: 'Avg Runtime', value: stats ? formatMs(stats.avg_runtime_ms) : '--', sub: 'per job' },
    { label: 'Avg Wait', value: stats ? formatMs(stats.avg_wait_ms) : '--', sub: 'queue wait' },
    { label: 'Workers', value: String(stats?.active_workers ?? 0), sub: 'active' },
  ];

  return (
    <div className="space-y-6">
      {/* Period selector */}
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Dashboard</h2>
        <ButtonGroup size="sm" variant="flat">
          {periods.map((p) => (
            <Button
              key={p}
              color={period === p ? 'primary' : 'default'}
              onPress={() => setPeriod(p)}
            >
              {p}
            </Button>
          ))}
        </ButtonGroup>
      </div>

      {/* Metrics cards row */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        {metricCards.map((card) => (
          <Card key={card.label} shadow="sm">
            <CardBody className="py-3 px-4">
              {statsLoading ? (
                <div className="space-y-2">
                  <Skeleton className="w-20 h-3 rounded-md" />
                  <Skeleton className="w-16 h-6 rounded-md" />
                </div>
              ) : (
                <>
                  <p className="text-xs text-default-500 uppercase tracking-wide">
                    {card.label}
                  </p>
                  <p className={`text-2xl font-bold mt-1 ${card.danger ? 'text-danger' : ''}`}>
                    {card.value ?? '--'}
                  </p>
                  <p className="text-xs text-default-400 mt-0.5">{card.sub}</p>
                </>
              )}
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <h3 className="text-sm font-semibold">Throughput (jobs/min)</h3>
          </CardHeader>
          <CardBody>
            {metricsLoading ? (
              <Skeleton className="w-full h-64 rounded-lg" />
            ) : (
              <ThroughputChart data={metrics?.data.throughput ?? []} />
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader className="pb-0">
            <h3 className="text-sm font-semibold">Avg Runtime</h3>
          </CardHeader>
          <CardBody>
            {metricsLoading ? (
              <Skeleton className="w-full h-64 rounded-lg" />
            ) : (
              <RuntimeChart data={metrics?.data.runtime ?? []} />
            )}
          </CardBody>
        </Card>
      </div>

      {/* Queue sizes */}
      <Card shadow="sm">
        <CardHeader className="pb-0">
          <h3 className="text-sm font-semibold">Queue Depths</h3>
        </CardHeader>
        <CardBody>
          {statsLoading ? (
            <div className="space-y-3">
              <Skeleton className="w-full h-6 rounded-md" />
              <Skeleton className="w-full h-6 rounded-md" />
              <Skeleton className="w-full h-6 rounded-md" />
            </div>
          ) : (
            <QueueSizeBar queueSizes={stats?.queue_sizes ?? {}} />
          )}
        </CardBody>
      </Card>
    </div>
  );
}

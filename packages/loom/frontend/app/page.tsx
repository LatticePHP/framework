"use client";

import { useEffect, useState, useCallback } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { StatCard } from "@/components/stat-card";
import { LineChart } from "@/components/line-chart";
import { QueueSizeBars } from "@/components/queue-size-bars";
import { useLoomStore, type Period } from "@/lib/store";
import { apiGet } from "@/lib/api";
import {
  StatsSchema,
  TimeSeriesMetricsSchema,
  type Stats,
  type TimeSeriesMetrics,
} from "@/lib/schemas";
import { formatMs } from "@/lib/utils";
import {
  Activity,
  AlertTriangle,
  Gauge,
  Clock,
  Hourglass,
  Users,
} from "lucide-react";

const periods: Period[] = ["5m", "1h", "6h", "24h", "7d"];

export default function DashboardPage() {
  const period = useLoomStore((s) => s.period);
  const setPeriod = useLoomStore((s) => s.setPeriod);
  const refreshInterval = useLoomStore((s) => s.refreshInterval);

  const [stats, setStats] = useState<Stats | null>(null);
  const [metrics, setMetrics] = useState<TimeSeriesMetrics | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);
  const [metricsLoading, setMetricsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      const raw = await apiGet<unknown>("/stats", { period });
      setStats(StatsSchema.parse(raw));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load stats");
    } finally {
      setStatsLoading(false);
    }
  }, [period]);

  const fetchMetrics = useCallback(async () => {
    try {
      const raw = await apiGet<unknown>("/metrics", { period });
      setMetrics(TimeSeriesMetricsSchema.parse(raw));
    } catch {
      // metrics error is secondary; stats error takes priority
    } finally {
      setMetricsLoading(false);
    }
  }, [period]);

  useEffect(() => {
    void fetchStats();
    void fetchMetrics();
  }, [fetchStats, fetchMetrics]);

  useEffect(() => {
    if (!refreshInterval) return;
    const id = setInterval(() => {
      void fetchStats();
      void fetchMetrics();
    }, refreshInterval);
    return () => clearInterval(id);
  }, [refreshInterval, fetchStats, fetchMetrics]);

  if (error && !stats) {
    return (
      <div className="flex flex-col items-center justify-center gap-4 py-20">
        <p className="text-sm text-destructive">Failed to connect to Loom API</p>
        <Button
          size="sm"
          onClick={() => {
            setStatsLoading(true);
            void fetchStats();
          }}
        >
          Retry
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Period selector */}
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Dashboard</h2>
        <div className="flex gap-1">
          {periods.map((p) => (
            <Button
              key={p}
              size="sm"
              variant={period === p ? "default" : "outline"}
              onClick={() => setPeriod(p)}
            >
              {p}
            </Button>
          ))}
        </div>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        <StatCard
          label="Throughput"
          value={`${stats?.throughput_per_minute ?? 0}/min`}
          sub="jobs per minute"
          icon={Gauge}
          loading={statsLoading}
        />
        <StatCard
          label="Avg Runtime"
          value={stats ? formatMs(stats.avg_runtime_ms) : "--"}
          sub="per job"
          icon={Clock}
          loading={statsLoading}
        />
        <StatCard
          label="Pending"
          value={
            stats?.queue_sizes
              ? Object.values(stats.queue_sizes)
                  .reduce((a, b) => a + b, 0)
                  .toLocaleString()
              : "0"
          }
          sub="in all queues"
          icon={Hourglass}
          loading={statsLoading}
        />
        <StatCard
          label="Failed"
          value={stats?.total_failed.toLocaleString() ?? "0"}
          sub={`${stats?.failed_last_hour ?? 0} last hour`}
          icon={AlertTriangle}
          danger={(stats?.total_failed ?? 0) > 0}
          loading={statsLoading}
        />
        <StatCard
          label="Processed"
          value={stats?.total_processed.toLocaleString() ?? "0"}
          sub={`${stats?.processed_last_hour ?? 0} last hour`}
          icon={Activity}
          loading={statsLoading}
        />
        <StatCard
          label="Workers"
          value={String(stats?.active_workers ?? 0)}
          sub="active"
          icon={Users}
          loading={statsLoading}
        />
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm">Throughput (jobs/min)</CardTitle>
          </CardHeader>
          <CardContent>
            {metricsLoading ? (
              <Skeleton className="h-64 w-full rounded-lg" />
            ) : (
              <LineChart
                data={metrics?.data.throughput ?? []}
                dataKey="count"
                label="Jobs"
                emptyMessage="No throughput data for this period"
              />
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm">Avg Runtime</CardTitle>
          </CardHeader>
          <CardContent>
            {metricsLoading ? (
              <Skeleton className="h-64 w-full rounded-lg" />
            ) : (
              <LineChart
                data={metrics?.data.runtime ?? []}
                dataKey="avg_runtime_ms"
                label="Avg Runtime"
                color="var(--color-secondary)"
                yFormatter={(val) => formatMs(val)}
                emptyMessage="No runtime data for this period"
              />
            )}
          </CardContent>
        </Card>
      </div>

      {/* Queue sizes */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm">Queue Depths</CardTitle>
        </CardHeader>
        <CardContent>
          {statsLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-6 w-full rounded-md" />
              <Skeleton className="h-6 w-full rounded-md" />
              <Skeleton className="h-6 w-full rounded-md" />
            </div>
          ) : (
            <QueueSizeBars queueSizes={stats?.queue_sizes ?? {}} />
          )}
        </CardContent>
      </Card>
    </div>
  );
}

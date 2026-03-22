"use client";

import { useRouter } from "next/navigation";
import { useMetricsOverview } from "@/lib/hooks";
import MetricCard from "@/components/metric-card";
import { Loader2 } from "lucide-react";

export default function OverviewPage() {
  const { data, isLoading, error } = useMetricsOverview();
  const router = useRouter();

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="flex items-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>Loading metrics...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-lg font-semibold mb-2 text-red-500 dark:text-red-400">
          Failed to load metrics
        </p>
        <p className="text-sm text-muted-foreground">
          {error instanceof Error ? error.message : "Unknown error"}
        </p>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="text-center py-12 text-muted-foreground">
        No metrics available yet.
      </div>
    );
  }

  const formatMetricValue = (metric: {
    value: number;
    unit?: string;
  }): string => {
    const v = metric.value;
    if (metric.unit === "ms") return `${v.toFixed(0)}ms`;
    if (metric.unit === "%") return `${v.toFixed(1)}%`;
    if (metric.unit === "/min") return `${v.toFixed(0)}`;
    if (v >= 1000) return `${(v / 1000).toFixed(1)}k`;
    return v.toFixed(v < 10 ? 1 : 0);
  };

  return (
    <div>
      <h1 className="text-xl font-bold mb-6">Production Overview</h1>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <MetricCard
          title="Requests / min"
          value={formatMetricValue(data.requests_per_minute)}
          unit={data.requests_per_minute.unit}
          trend={data.requests_per_minute.trend}
          changePercent={data.requests_per_minute.change_percent}
          color="primary"
        />

        <MetricCard
          title="Avg Response Time"
          value={formatMetricValue(data.avg_response_time)}
          unit="ms"
          trend={data.avg_response_time.trend}
          changePercent={data.avg_response_time.change_percent}
          color={
            data.avg_response_time.value > 500 ? "danger" : "success"
          }
        />

        <MetricCard
          title="P99 Response Time"
          value={formatMetricValue(data.p99_response_time)}
          unit="ms"
          trend={data.p99_response_time.trend}
          changePercent={data.p99_response_time.change_percent}
          color={
            data.p99_response_time.value > 1000 ? "danger" : "warning"
          }
          onClick={() => router.push("/nightwatch/prod/slow-requests")}
        />

        <MetricCard
          title="Error Rate"
          value={formatMetricValue(data.error_rate)}
          unit="%"
          trend={data.error_rate.trend}
          changePercent={data.error_rate.change_percent}
          color={
            data.error_rate.value > 5
              ? "danger"
              : data.error_rate.value > 1
                ? "warning"
                : "success"
          }
          onClick={() => router.push("/nightwatch/prod/exceptions")}
        />

        <MetricCard
          title="Slow Queries"
          value={formatMetricValue(data.slow_queries_count)}
          trend={data.slow_queries_count.trend}
          changePercent={data.slow_queries_count.change_percent}
          color={
            data.slow_queries_count.value > 10 ? "danger" : "warning"
          }
          onClick={() => router.push("/nightwatch/prod/slow-queries")}
        />

        <MetricCard
          title="Cache Hit Ratio"
          value={formatMetricValue(data.cache_hit_ratio)}
          unit="%"
          trend={data.cache_hit_ratio.trend}
          changePercent={data.cache_hit_ratio.change_percent}
          color={
            data.cache_hit_ratio.value > 90
              ? "success"
              : data.cache_hit_ratio.value > 70
                ? "warning"
                : "danger"
          }
        />

        <MetricCard
          title="Queue Throughput"
          value={formatMetricValue(data.queue_throughput)}
          unit="/min"
          trend={data.queue_throughput.trend}
          changePercent={data.queue_throughput.change_percent}
          color="primary"
        />

        {data.cpu_usage && (
          <MetricCard
            title="CPU Usage"
            value={formatMetricValue(data.cpu_usage)}
            unit="%"
            trend={data.cpu_usage.trend}
            changePercent={data.cpu_usage.change_percent}
            color={
              data.cpu_usage.value > 80
                ? "danger"
                : data.cpu_usage.value > 60
                  ? "warning"
                  : "success"
            }
          />
        )}

        {data.memory_usage && (
          <MetricCard
            title="Memory Usage"
            value={formatMetricValue(data.memory_usage)}
            unit="%"
            trend={data.memory_usage.trend}
            changePercent={data.memory_usage.change_percent}
            color={
              data.memory_usage.value > 85
                ? "danger"
                : data.memory_usage.value > 70
                  ? "warning"
                  : "success"
            }
          />
        )}

        {data.disk_usage && (
          <MetricCard
            title="Disk Usage"
            value={formatMetricValue(data.disk_usage)}
            unit="%"
            trend={data.disk_usage.trend}
            changePercent={data.disk_usage.change_percent}
            color={
              data.disk_usage.value > 90
                ? "danger"
                : data.disk_usage.value > 75
                  ? "warning"
                  : "success"
            }
          />
        )}
      </div>
    </div>
  );
}

"use client";

import { useWorkflowStats } from "@/lib/api";
import { StatCard } from "@/components/dashboard/stat-card";
import { BarChartCard } from "@/components/charts/bar-chart";
import { AreaChartCard } from "@/components/charts/area-chart";
import { ErrorState } from "@/components/feedback/error-state";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { formatDuration } from "@/lib/formatters";
import { Activity, CheckCircle2, XCircle, Ban, Hash, Clock } from "lucide-react";

const STATUS_COLORS = {
  Running: "hsl(var(--chart-1))",
  Completed: "hsl(var(--chart-2))",
  Failed: "hsl(var(--chart-5))",
  Cancelled: "hsl(var(--chart-3))",
};

export default function StatsPage() {
  const { data: statsResponse, isLoading, isError, error, refetch } = useWorkflowStats();

  if (isLoading) {
    return (
      <div className="flex flex-col gap-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Statistics</h2>
          <p className="text-sm text-muted-foreground mt-1">
            Aggregate workflow execution metrics
          </p>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          {Array.from({ length: 5 }).map((_, i) => (
            <StatCard key={i} title="" value="" loading />
          ))}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Skeleton className="h-80 rounded-lg" />
          <Skeleton className="h-80 rounded-lg" />
        </div>
      </div>
    );
  }

  if (isError || !statsResponse) {
    return (
      <div className="flex flex-col gap-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Statistics</h2>
          <p className="text-sm text-muted-foreground mt-1">
            Aggregate workflow execution metrics
          </p>
        </div>
        <ErrorState
          title="Failed to Load Statistics"
          message={error instanceof Error ? error.message : "Unknown error"}
          retry={() => void refetch()}
        />
      </div>
    );
  }

  const stats = statsResponse.data;
  const total = stats.running + stats.completed + stats.failed + stats.cancelled;

  const barData = [
    { name: "Running", value: stats.running, color: STATUS_COLORS.Running },
    { name: "Completed", value: stats.completed, color: STATUS_COLORS.Completed },
    { name: "Failed", value: stats.failed, color: STATUS_COLORS.Failed },
    { name: "Cancelled", value: stats.cancelled, color: STATUS_COLORS.Cancelled },
  ];

  const areaData = [
    { name: "Running", count: stats.running },
    { name: "Completed", count: stats.completed },
    { name: "Failed", count: stats.failed },
    { name: "Cancelled", count: stats.cancelled },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Statistics</h2>
        <p className="text-sm text-muted-foreground mt-1">
          Aggregate workflow execution metrics
        </p>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <StatCard title="Total" value={total} icon={Hash} />
        <StatCard title="Running" value={stats.running} icon={Activity} />
        <StatCard title="Completed" value={stats.completed} icon={CheckCircle2} />
        <StatCard title="Failed" value={stats.failed} icon={XCircle} />
        <StatCard
          title="Avg Duration"
          value={formatDuration(stats.avg_duration_ms)}
          icon={Clock}
        />
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <BarChartCard title="Workflow Status Distribution" data={barData} />
        <AreaChartCard
          title="Status Overview"
          data={areaData}
          dataKey="count"
          color="hsl(var(--chart-1))"
        />
      </div>

      {/* Summary table */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-lg">Summary</CardTitle>
        </CardHeader>
        <Separator />
        <CardContent className="pt-4">
          <div className="flex flex-col gap-3">
            {barData.map((item) => (
              <div
                key={item.name}
                className="flex items-center justify-between py-2 border-b border-border last:border-0"
              >
                <div className="flex items-center gap-3">
                  <div
                    className="h-3 w-3 rounded-full"
                    style={{ backgroundColor: item.color }}
                  />
                  <span className="text-sm">{item.name}</span>
                </div>
                <div className="flex items-center gap-4">
                  <span className="text-sm font-mono font-bold">{item.value}</span>
                  <span className="text-xs text-muted-foreground w-12 text-right">
                    {total > 0
                      ? `${((item.value / total) * 100).toFixed(1)}%`
                      : "0%"}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

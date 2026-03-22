"use client";

import { useEffect, useState } from "react";
import {
  BarChart3,
  AlertTriangle,
  AlertCircle,
  Info,
  Skull,
  CheckCircle,
  XCircle,
  EyeOff,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { usePrismStore } from "@/lib/store";
import { fetchStats } from "@/lib/api";
import type { Stats } from "@/lib/schemas";

const DEMO_STATS: Stats = {
  total_issues: 47,
  unresolved: 28,
  resolved: 14,
  ignored: 5,
  total_events: 3842,
  by_level: { error: 22, warning: 12, fatal: 5, info: 8 },
};

function StatCard({
  title,
  value,
  icon: Icon,
  color,
}: {
  title: string;
  value: number;
  icon: React.ComponentType<{ className?: string }>;
  color: string;
}) {
  return (
    <Card>
      <CardContent className="p-4">
        <div className="flex items-center gap-3">
          <div className={"rounded-md p-2 " + color}>
            <Icon className="h-5 w-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground uppercase tracking-wider">{title}</p>
            <p className="text-2xl font-bold mt-0.5">{value.toLocaleString()}</p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function DonutChart({ stats }: { stats: Stats }) {
  const segments = [
    { label: "Unresolved", value: stats.unresolved, color: "#ef4444" },
    { label: "Resolved", value: stats.resolved, color: "#22c55e" },
    { label: "Ignored", value: stats.ignored, color: "#6b7280" },
  ];

  const total = segments.reduce((sum, s) => sum + s.value, 0);
  if (total === 0) return null;

  let cumulativePercent = 0;
  const slices = segments.map((seg) => {
    const percent = (seg.value / total) * 100;
    const startAngle = (cumulativePercent / 100) * 360;
    const endAngle = ((cumulativePercent + percent) / 100) * 360;
    cumulativePercent += percent;

    const startRad = ((startAngle - 90) * Math.PI) / 180;
    const endRad = ((endAngle - 90) * Math.PI) / 180;

    const x1 = 50 + 40 * Math.cos(startRad);
    const y1 = 50 + 40 * Math.sin(startRad);
    const x2 = 50 + 40 * Math.cos(endRad);
    const y2 = 50 + 40 * Math.sin(endRad);

    const largeArc = percent > 50 ? 1 : 0;

    const d = [
      "M 50 50",
      `L ${x1} ${y1}`,
      `A 40 40 0 ${largeArc} 1 ${x2} ${y2}`,
      "Z",
    ].join(" ");

    return { ...seg, d, percent };
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Status Distribution</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex items-center gap-8">
          <div className="relative">
            <svg viewBox="0 0 100 100" className="h-40 w-40">
              {slices.map((slice) => (
                <path
                  key={slice.label}
                  d={slice.d}
                  fill={slice.color}
                  className="transition-opacity hover:opacity-80"
                />
              ))}
              <circle cx="50" cy="50" r="24" className="fill-background" />
              <text
                x="50"
                y="47"
                textAnchor="middle"
                className="fill-foreground text-lg font-bold"
                style={{ fontSize: "14px" }}
              >
                {total}
              </text>
              <text
                x="50"
                y="58"
                textAnchor="middle"
                className="fill-muted-foreground"
                style={{ fontSize: "6px" }}
              >
                issues
              </text>
            </svg>
          </div>

          <div className="space-y-2">
            {slices.map((slice) => (
              <div key={slice.label} className="flex items-center gap-2 text-sm">
                <span
                  className="h-3 w-3 rounded-full shrink-0"
                  style={{ backgroundColor: slice.color }}
                />
                <span className="text-muted-foreground">{slice.label}</span>
                <span className="font-medium ml-auto">{slice.value}</span>
                <span className="text-xs text-muted-foreground">
                  ({slice.percent.toFixed(0)}%)
                </span>
              </div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export default function StatsPage() {
  const selectedProject = usePrismStore((s) => s.selectedProject);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    fetchStats(selectedProject ?? undefined)
      .then((data) => !cancelled && setStats(data))
      .catch(() => !cancelled && setStats(DEMO_STATS))
      .finally(() => !cancelled && setLoading(false));
    return () => {
      cancelled = true;
    };
  }, [selectedProject]);

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-8 w-32 bg-muted rounded animate-pulse" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-muted rounded animate-pulse" />
          ))}
        </div>
      </div>
    );
  }

  const s = stats ?? DEMO_STATS;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <BarChart3 className="h-5 w-5 text-primary" />
        <h1 className="text-xl font-bold">Statistics</h1>
      </div>

      {/* Status cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          title="Total Issues"
          value={s.total_issues}
          icon={AlertCircle}
          color="bg-primary/10 text-primary"
        />
        <StatCard
          title="Unresolved"
          value={s.unresolved}
          icon={XCircle}
          color="bg-destructive/10 text-destructive"
        />
        <StatCard
          title="Resolved"
          value={s.resolved}
          icon={CheckCircle}
          color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
        />
        <StatCard
          title="Total Events"
          value={s.total_events}
          icon={BarChart3}
          color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
        />
      </div>

      {/* Level breakdown */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Issues by Level</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <StatCard
              title="Fatal"
              value={s.by_level.fatal ?? 0}
              icon={Skull}
              color="bg-red-600/10 text-red-600 dark:text-red-400"
            />
            <StatCard
              title="Error"
              value={s.by_level.error ?? 0}
              icon={AlertTriangle}
              color="bg-orange-500/10 text-orange-600 dark:text-orange-400"
            />
            <StatCard
              title="Warning"
              value={s.by_level.warning ?? 0}
              icon={AlertCircle}
              color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
            />
            <StatCard
              title="Info"
              value={s.by_level.info ?? 0}
              icon={Info}
              color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
            />
          </div>
        </CardContent>
      </Card>

      {/* Donut chart for status distribution */}
      <DonutChart stats={s} />

      {/* Ignored */}
      <div className="grid grid-cols-2 gap-4">
        <StatCard
          title="Ignored"
          value={s.ignored}
          icon={EyeOff}
          color="bg-muted text-muted-foreground"
        />
      </div>
    </div>
  );
}

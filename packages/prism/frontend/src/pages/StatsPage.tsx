import { useNavigate } from "react-router-dom";
import {
  Card,
  CardBody,
  CardHeader,
  Skeleton,
  Button,
} from "@nextui-org/react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Legend,
} from "recharts";
import { useStats } from "@/api/stats";
import { useProjectStore } from "@/stores/project";

const LEVEL_COLORS: Record<string, string> = {
  fatal: "#9333ea",
  error: "#ef4444",
  warning: "#f59e0b",
  info: "#3b82f6",
};

const STATUS_COLORS: Record<string, string> = {
  unresolved: "#ef4444",
  resolved: "#22c55e",
  ignored: "#71717a",
};

export function StatsPage() {
  const navigate = useNavigate();
  const selectedProjectId = useProjectStore((s) => s.selectedProjectId);
  const { data: stats, isLoading, error } = useStats(selectedProjectId);

  // No project selected
  if (!selectedProjectId) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-4">Stats</h2>
        <Card>
          <CardBody className="text-center py-16">
            <h3 className="text-lg font-semibold mb-2">
              Select a project first
            </h3>
            <p className="text-sm text-default-400 mb-4">
              Use the project selector in the sidebar to pick a project.
            </p>
            <Button
              color="primary"
              variant="flat"
              onPress={() => navigate("/")}
            >
              Go to Projects
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-6">Stats</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-28 rounded-xl" />
          ))}
        </div>
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-80 rounded-xl" />
          <Skeleton className="h-80 rounded-xl" />
        </div>
      </div>
    );
  }

  if (error || !stats) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-4">Stats</h2>
        <Card>
          <CardBody className="text-center py-12">
            <p className="text-danger mb-2">Failed to load stats</p>
            <p className="text-sm text-default-400">
              {error instanceof Error ? error.message : "Unknown error"}
            </p>
          </CardBody>
        </Card>
      </div>
    );
  }

  // Prepare chart data
  const levelChartData = Object.entries(stats.by_level).map(
    ([level, count]) => ({
      name: level.charAt(0).toUpperCase() + level.slice(1),
      count,
      fill: LEVEL_COLORS[level] ?? "#71717a",
    }),
  );

  const statusChartData = [
    {
      name: "Unresolved",
      value: stats.unresolved,
      fill: STATUS_COLORS.unresolved,
    },
    { name: "Resolved", value: stats.resolved, fill: STATUS_COLORS.resolved },
    { name: "Ignored", value: stats.ignored, fill: STATUS_COLORS.ignored },
  ].filter((d) => d.value > 0);

  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-6 text-foreground">Stats</h2>

      {/* Summary cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <Card className="border border-default-200">
          <CardBody className="p-5">
            <p className="text-xs text-default-400 uppercase tracking-wider mb-1">
              Total Issues
            </p>
            <p className="text-3xl font-bold text-foreground">
              {stats.total_issues.toLocaleString()}
            </p>
          </CardBody>
        </Card>

        <Card className="border border-danger-200 dark:border-danger-500/30">
          <CardBody className="p-5">
            <p className="text-xs text-danger uppercase tracking-wider mb-1">
              Unresolved
            </p>
            <p className="text-3xl font-bold text-danger">
              {stats.unresolved.toLocaleString()}
            </p>
          </CardBody>
        </Card>

        <Card className="border border-success-200 dark:border-success-500/30">
          <CardBody className="p-5">
            <p className="text-xs text-success uppercase tracking-wider mb-1">
              Resolved
            </p>
            <p className="text-3xl font-bold text-success">
              {stats.resolved.toLocaleString()}
            </p>
          </CardBody>
        </Card>

        <Card className="border border-default-200">
          <CardBody className="p-5">
            <p className="text-xs text-default-400 uppercase tracking-wider mb-1">
              Total Events
            </p>
            <p className="text-3xl font-bold text-foreground">
              {stats.total_events.toLocaleString()}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Charts */}
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Issues by Level — Bar chart */}
        <Card className="border border-default-200">
          <CardHeader className="pb-0">
            <h3 className="text-sm font-semibold">Issues by Level</h3>
          </CardHeader>
          <CardBody>
            {levelChartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={280}>
                <BarChart data={levelChartData}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.15} />
                  <XAxis
                    dataKey="name"
                    tick={{ fontSize: 12 }}
                    axisLine={false}
                    tickLine={false}
                  />
                  <YAxis
                    tick={{ fontSize: 12 }}
                    axisLine={false}
                    tickLine={false}
                    allowDecimals={false}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "hsl(var(--nextui-content1))",
                      border: "1px solid hsl(var(--nextui-default-200))",
                      borderRadius: "8px",
                      fontSize: "12px",
                    }}
                  />
                  <Bar dataKey="count" radius={[4, 4, 0, 0]}>
                    {levelChartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.fill} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-[280px] text-default-400 text-sm">
                No data to display
              </div>
            )}
          </CardBody>
        </Card>

        {/* Issues by Status — Pie chart */}
        <Card className="border border-default-200">
          <CardHeader className="pb-0">
            <h3 className="text-sm font-semibold">Issues by Status</h3>
          </CardHeader>
          <CardBody>
            {statusChartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={280}>
                <PieChart>
                  <Pie
                    data={statusChartData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={100}
                    paddingAngle={3}
                    dataKey="value"
                    label={({ name, percent }) =>
                      `${name} ${(percent * 100).toFixed(0)}%`
                    }
                    labelLine={false}
                  >
                    {statusChartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.fill} />
                    ))}
                  </Pie>
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "hsl(var(--nextui-content1))",
                      border: "1px solid hsl(var(--nextui-default-200))",
                      borderRadius: "8px",
                      fontSize: "12px",
                    }}
                  />
                  <Legend
                    iconType="circle"
                    wrapperStyle={{ fontSize: "12px" }}
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-[280px] text-default-400 text-sm">
                No data to display
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

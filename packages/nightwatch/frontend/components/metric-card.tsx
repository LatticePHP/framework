import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { ArrowUp, ArrowDown, ArrowRight } from "lucide-react";

interface MetricCardProps {
  title: string;
  value: string | number;
  unit?: string;
  trend?: "up" | "down" | "stable";
  changePercent?: number;
  color?: "default" | "primary" | "success" | "warning" | "danger";
  onClick?: () => void;
}

const borderColorMap: Record<string, string> = {
  default: "border-l-muted-foreground/30",
  primary: "border-l-blue-500",
  success: "border-l-emerald-500",
  warning: "border-l-amber-500",
  danger: "border-l-red-500",
};

function getTrendColor(trend: "up" | "down" | "stable"): string {
  if (trend === "stable") return "text-muted-foreground";
  if (trend === "up") return "text-red-500";
  return "text-emerald-500";
}

function TrendIcon({ trend }: { trend: "up" | "down" | "stable" }) {
  if (trend === "up") return <ArrowUp className="h-3 w-3" />;
  if (trend === "down") return <ArrowDown className="h-3 w-3" />;
  return <ArrowRight className="h-3 w-3" />;
}

export default function MetricCard({
  title,
  value,
  unit,
  trend,
  changePercent,
  color = "default",
  onClick,
}: MetricCardProps) {
  return (
    <Card
      className={cn(
        "border-l-4 cursor-default",
        borderColorMap[color] ?? borderColorMap["default"],
        onClick && "cursor-pointer hover:bg-muted/50 transition-colors",
      )}
      onClick={onClick}
    >
      <CardContent className="p-4">
        <p className="text-xs text-muted-foreground uppercase tracking-wider mb-1">
          {title}
        </p>
        <div className="flex items-baseline gap-1.5">
          <span className="text-2xl font-bold text-foreground">
            {typeof value === "number" ? value.toLocaleString() : value}
          </span>
          {unit && (
            <span className="text-sm text-muted-foreground">{unit}</span>
          )}
        </div>
        {trend && (
          <div
            className={cn(
              "flex items-center gap-1 mt-1 text-xs",
              getTrendColor(trend),
            )}
          >
            <TrendIcon trend={trend} />
            {changePercent !== undefined && (
              <span>{Math.abs(changePercent).toFixed(1)}%</span>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

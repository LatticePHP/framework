import { Badge } from "@/components/ui/badge";

interface DurationBadgeProps {
  ms: number;
}

function getDurationVariant(ms: number): "success" | "warning" | "danger" {
  if (ms >= 1000) return "danger";
  if (ms >= 200) return "warning";
  return "success";
}

function formatDuration(ms: number): string {
  if (ms >= 1000) return `${(ms / 1000).toFixed(2)}s`;
  return `${ms.toFixed(0)}ms`;
}

export default function DurationBadge({ ms }: DurationBadgeProps) {
  return <Badge variant={getDurationVariant(ms)}>{formatDuration(ms)}</Badge>;
}

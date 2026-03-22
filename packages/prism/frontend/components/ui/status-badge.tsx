import { Badge } from "./badge";
import type { ErrorLevel, IssueStatus } from "@/lib/schemas";

const LEVEL_VARIANT: Record<ErrorLevel, "fatal" | "error" | "warning" | "info"> = {
  fatal: "fatal",
  error: "error",
  warning: "warning",
  info: "info",
};

const STATUS_VARIANT: Record<IssueStatus, "destructive" | "success" | "secondary"> = {
  unresolved: "destructive",
  resolved: "success",
  ignored: "secondary",
};

export function LevelBadge({ level }: { level: ErrorLevel }) {
  return (
    <Badge variant={LEVEL_VARIANT[level]}>
      {level.charAt(0).toUpperCase() + level.slice(1)}
    </Badge>
  );
}

export function StatusBadge({ status }: { status: IssueStatus }) {
  return (
    <Badge variant={STATUS_VARIANT[status]}>
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </Badge>
  );
}

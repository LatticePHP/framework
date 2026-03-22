import { Badge } from "@/components/ui/badge";
import type { WorkflowStatus } from "@/lib/schemas";
import { cn } from "@/lib/utils";

const STATUS_CONFIG: Record<
  WorkflowStatus,
  { variant: "default" | "secondary" | "destructive" | "outline" | "success" | "warning"; label: string }
> = {
  running: { variant: "default", label: "Running" },
  completed: { variant: "success", label: "Completed" },
  failed: { variant: "destructive", label: "Failed" },
  cancelled: { variant: "warning", label: "Cancelled" },
  terminated: { variant: "secondary", label: "Terminated" },
  timed_out: { variant: "outline", label: "Timed Out" },
};

interface StatusBadgeProps {
  status: WorkflowStatus;
  className?: string;
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const config = STATUS_CONFIG[status] ?? { variant: "secondary" as const, label: status };

  return (
    <Badge variant={config.variant} className={cn("capitalize", className)}>
      {config.label}
    </Badge>
  );
}

import { Chip } from "@nextui-org/react";
import type { IssueStatus } from "@/schemas/issue";

interface StatusChipProps {
  status: IssueStatus;
  size?: "sm" | "md" | "lg";
}

const statusConfig: Record<
  IssueStatus,
  {
    color: "danger" | "success" | "default";
    variant: "flat" | "bordered";
    icon: string;
    label: string;
  }
> = {
  unresolved: {
    color: "danger",
    variant: "flat",
    icon: "\u25CF",
    label: "Unresolved",
  },
  resolved: {
    color: "success",
    variant: "flat",
    icon: "\u2713",
    label: "Resolved",
  },
  ignored: {
    color: "default",
    variant: "bordered",
    icon: "\u2014",
    label: "Ignored",
  },
};

export function StatusChip({ status, size = "sm" }: StatusChipProps) {
  const config = statusConfig[status];

  return (
    <Chip color={config.color} variant={config.variant} size={size}>
      <span className="mr-1">{config.icon}</span>
      {config.label}
    </Chip>
  );
}

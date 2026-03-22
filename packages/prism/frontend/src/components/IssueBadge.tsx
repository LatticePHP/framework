import { Chip } from "@nextui-org/react";
import type { IssueLevel } from "@/schemas/issue";

interface IssueBadgeProps {
  level: IssueLevel;
  size?: "sm" | "md" | "lg";
}

const levelConfig: Record<
  IssueLevel,
  { color: "danger" | "warning" | "secondary" | "primary"; label: string }
> = {
  fatal: { color: "secondary", label: "Fatal" },
  error: { color: "danger", label: "Error" },
  warning: { color: "warning", label: "Warning" },
  info: { color: "primary", label: "Info" },
};

export function IssueBadge({ level, size = "sm" }: IssueBadgeProps) {
  const config = levelConfig[level];

  return (
    <Chip
      color={config.color}
      variant="flat"
      size={size}
      classNames={{
        base: "font-mono uppercase tracking-wider",
        content: "text-xs font-semibold",
      }}
    >
      {config.label}
    </Chip>
  );
}

import { Chip } from '@nextui-org/react';
import type { WorkflowStatus } from '@/schemas/workflow';

const STATUS_CONFIG: Record<
  WorkflowStatus,
  { color: 'success' | 'primary' | 'danger' | 'warning' | 'default' | 'secondary'; label: string }
> = {
  running: { color: 'primary', label: 'Running' },
  completed: { color: 'success', label: 'Completed' },
  failed: { color: 'danger', label: 'Failed' },
  cancelled: { color: 'warning', label: 'Cancelled' },
  terminated: { color: 'default', label: 'Terminated' },
  timed_out: { color: 'secondary', label: 'Timed Out' },
};

interface StatusBadgeProps {
  status: WorkflowStatus;
  size?: 'sm' | 'md' | 'lg';
}

export function StatusBadge({ status, size = 'sm' }: StatusBadgeProps) {
  const config = STATUS_CONFIG[status] ?? { color: 'default' as const, label: status };

  return (
    <Chip
      color={config.color}
      variant="flat"
      size={size}
      classNames={{
        base: 'transition-all duration-300',
      }}
    >
      {config.label}
    </Chip>
  );
}

import { Chip } from '@nextui-org/react';

const statusConfig: Record<string, { color: 'warning' | 'primary' | 'success' | 'danger' | 'default'; label: string }> = {
  pending: { color: 'warning', label: 'Pending' },
  processing: { color: 'primary', label: 'Processing' },
  completed: { color: 'success', label: 'Completed' },
  failed: { color: 'danger', label: 'Failed' },
  retried: { color: 'default', label: 'Retried' },
};

interface JobStatusBadgeProps {
  status: string;
}

export default function JobStatusBadge({ status }: JobStatusBadgeProps) {
  const config = statusConfig[status] ?? { color: 'default' as const, label: status };

  return (
    <Chip size="sm" variant="flat" color={config.color}>
      {config.label}
    </Chip>
  );
}

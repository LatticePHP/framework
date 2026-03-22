import { Chip } from '@nextui-org/react';

interface DurationBadgeProps {
  ms: number;
  size?: 'sm' | 'md' | 'lg';
}

function getDurationColor(ms: number): 'success' | 'warning' | 'danger' {
  if (ms >= 1000) return 'danger';
  if (ms >= 200) return 'warning';
  return 'success';
}

function formatDuration(ms: number): string {
  if (ms >= 1000) return `${(ms / 1000).toFixed(2)}s`;
  return `${ms.toFixed(0)}ms`;
}

export default function DurationBadge({ ms, size = 'sm' }: DurationBadgeProps) {
  return (
    <Chip color={getDurationColor(ms)} variant="flat" size={size}>
      {formatDuration(ms)}
    </Chip>
  );
}

import { Progress } from '@nextui-org/react';

interface QueueSizeBarProps {
  queueSizes: Record<string, number>;
}

export default function QueueSizeBar({ queueSizes }: QueueSizeBarProps) {
  const entries = Object.entries(queueSizes);
  if (entries.length === 0) {
    return (
      <div className="text-sm text-default-400 py-4 text-center">
        No active queues
      </div>
    );
  }

  const maxSize = Math.max(...entries.map(([, size]) => size), 1);

  const colors: Array<'primary' | 'secondary' | 'success' | 'warning' | 'danger'> = [
    'primary',
    'secondary',
    'success',
    'warning',
    'danger',
  ];

  return (
    <div className="space-y-3">
      {entries.map(([queue, size], idx) => (
        <div key={queue}>
          <div className="flex justify-between text-sm mb-1">
            <span className="font-medium">{queue}</span>
            <span className="text-default-500">
              {size.toLocaleString()} pending
            </span>
          </div>
          <Progress
            size="sm"
            value={(size / maxSize) * 100}
            color={colors[idx % colors.length]}
            aria-label={`${queue} queue depth`}
          />
        </div>
      ))}
    </div>
  );
}

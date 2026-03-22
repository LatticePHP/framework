"use client";

import { Progress } from "@/components/ui/progress";

const barColors = [
  "bg-blue-500",
  "bg-violet-500",
  "bg-emerald-500",
  "bg-amber-500",
  "bg-rose-500",
];

interface QueueSizeBarsProps {
  queueSizes: Record<string, number>;
}

export function QueueSizeBars({ queueSizes }: QueueSizeBarsProps) {
  const entries = Object.entries(queueSizes);

  if (entries.length === 0) {
    return (
      <div className="py-4 text-center text-sm text-muted-foreground">
        No active queues
      </div>
    );
  }

  const maxSize = Math.max(...entries.map(([, size]) => size), 1);

  return (
    <div className="space-y-3">
      {entries.map(([queue, size], idx) => (
        <div key={queue}>
          <div className="mb-1 flex justify-between text-sm">
            <span className="font-medium">{queue}</span>
            <span className="text-muted-foreground">
              {size.toLocaleString()} pending
            </span>
          </div>
          <Progress
            value={(size / maxSize) * 100}
            className="h-2"
            indicatorClassName={barColors[idx % barColors.length]}
          />
        </div>
      ))}
    </div>
  );
}

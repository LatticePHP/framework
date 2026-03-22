import { Tooltip } from "@nextui-org/react";

interface EventCountProps {
  count: number;
  trend?: "increasing" | "decreasing" | "stable";
  className?: string;
}

function formatCount(count: number): string {
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`;
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}k`;
  return count.toLocaleString();
}

const trendIcons: Record<string, { symbol: string; color: string }> = {
  increasing: { symbol: "\u2191", color: "text-danger" },
  decreasing: { symbol: "\u2193", color: "text-success" },
  stable: { symbol: "\u2192", color: "text-default-400" },
};

export function EventCount({ count, trend, className }: EventCountProps) {
  const trendInfo = trend ? trendIcons[trend] : null;

  return (
    <Tooltip content={`${count.toLocaleString()} events`}>
      <span
        className={`inline-flex items-center gap-1 font-mono text-sm ${className ?? ""}`}
      >
        <span>{formatCount(count)}</span>
        {trendInfo && (
          <span className={trendInfo.color}>{trendInfo.symbol}</span>
        )}
      </span>
    </Tooltip>
  );
}

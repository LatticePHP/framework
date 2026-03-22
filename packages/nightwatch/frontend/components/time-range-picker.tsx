"use client";

import { Button } from "@/components/ui/button";
import { useFiltersStore } from "@/lib/store";
import type { TimePeriod } from "@/lib/schemas";
import { cn } from "@/lib/utils";

const TIME_RANGES: { label: string; value: TimePeriod }[] = [
  { label: "1h", value: "1h" },
  { label: "6h", value: "6h" },
  { label: "24h", value: "24h" },
  { label: "7d", value: "7d" },
  { label: "30d", value: "30d" },
];

export default function TimeRangePicker() {
  const { timeRange, setTimeRange } = useFiltersStore();

  return (
    <div className="flex items-center gap-1 rounded-lg border border-border p-1">
      {TIME_RANGES.map((range) => (
        <Button
          key={range.value}
          variant={timeRange === range.value ? "default" : "ghost"}
          size="sm"
          onClick={() => setTimeRange(range.value)}
          className={cn("h-7 px-3 text-xs", timeRange !== range.value && "text-muted-foreground")}
        >
          {range.label}
        </Button>
      ))}
    </div>
  );
}

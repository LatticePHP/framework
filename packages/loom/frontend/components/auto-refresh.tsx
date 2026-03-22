"use client";

import { Select } from "@/components/ui/select";
import { useLoomStore, type RefreshInterval } from "@/lib/store";

const intervals = [
  { value: 0, label: "Off" },
  { value: 5000, label: "5s" },
  { value: 15000, label: "15s" },
  { value: 30000, label: "30s" },
  { value: 60000, label: "60s" },
];

export function AutoRefresh() {
  const refreshInterval = useLoomStore((s) => s.refreshInterval);
  const setRefreshInterval = useLoomStore((s) => s.setRefreshInterval);

  return (
    <div className="flex items-center gap-2">
      <span className="text-xs text-muted-foreground">Refresh:</span>
      <Select
        value={String(refreshInterval)}
        onChange={(e) =>
          setRefreshInterval(Number(e.target.value) as RefreshInterval)
        }
        className="h-8 w-20 text-xs"
        aria-label="Auto-refresh interval"
      >
        {intervals.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </Select>
      {refreshInterval > 0 && (
        <span className="relative flex h-2 w-2">
          <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
          <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
        </span>
      )}
    </div>
  );
}

import { Select, SelectItem } from '@nextui-org/react';
import { useFiltersStore } from '@/stores/filters';

const intervals = [
  { value: '0', label: 'Off' },
  { value: '5000', label: '5s' },
  { value: '15000', label: '15s' },
  { value: '30000', label: '30s' },
  { value: '60000', label: '60s' },
] as const;

export default function AutoRefresh() {
  const refreshInterval = useFiltersStore((s) => s.refreshInterval);
  const setRefreshInterval = useFiltersStore((s) => s.setRefreshInterval);

  return (
    <div className="flex items-center gap-2">
      <span className="text-xs text-default-500">Refresh:</span>
      <Select
        size="sm"
        variant="flat"
        selectedKeys={[String(refreshInterval)]}
        onSelectionChange={(keys) => {
          const selected = Array.from(keys)[0];
          if (selected !== undefined) {
            setRefreshInterval(
              Number(selected) as 0 | 5000 | 15000 | 30000 | 60000,
            );
          }
        }}
        className="w-24"
        aria-label="Auto-refresh interval"
      >
        {intervals.map((opt) => (
          <SelectItem key={opt.value}>{opt.label}</SelectItem>
        ))}
      </Select>
      {refreshInterval > 0 && (
        <span className="relative flex h-2 w-2">
          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75" />
          <span className="relative inline-flex rounded-full h-2 w-2 bg-success" />
        </span>
      )}
    </div>
  );
}

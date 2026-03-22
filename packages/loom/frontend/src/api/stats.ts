import { useQuery } from '@tanstack/react-query';
import { apiGet } from './client';
import { StatsSchema, type Stats } from '@/schemas/job';
import { useFiltersStore } from '@/stores/filters';

export function useDashboardStats() {
  const period = useFiltersStore((s) => s.period);
  const refreshInterval = useFiltersStore((s) => s.refreshInterval);

  return useQuery<Stats>({
    queryKey: ['loom', 'stats', period],
    queryFn: async () => {
      const raw = await apiGet<unknown>('/stats', { period });
      return StatsSchema.parse(raw);
    },
    refetchInterval: refreshInterval || false,
  });
}

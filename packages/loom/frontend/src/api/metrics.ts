import { useQuery } from '@tanstack/react-query';
import { apiGet } from './client';
import {
  TimeSeriesMetricsSchema,
  WorkersResponseSchema,
  type TimeSeriesMetrics,
  type Worker,
} from '@/schemas/job';
import { useFiltersStore } from '@/stores/filters';

export function useQueueMetrics() {
  const period = useFiltersStore((s) => s.period);
  const queue = useFiltersStore((s) => s.selectedQueue);
  const refreshInterval = useFiltersStore((s) => s.refreshInterval);

  return useQuery<TimeSeriesMetrics>({
    queryKey: ['loom', 'metrics', { period, queue }],
    queryFn: async () => {
      const raw = await apiGet<unknown>('/metrics', { period, queue });
      return TimeSeriesMetricsSchema.parse(raw);
    },
    refetchInterval: refreshInterval || false,
  });
}

export function useWorkerList() {
  const refreshInterval = useFiltersStore((s) => s.refreshInterval);

  return useQuery<Worker[]>({
    queryKey: ['loom', 'workers'],
    queryFn: async () => {
      const raw = await apiGet<unknown>('/workers');
      const parsed = WorkersResponseSchema.parse(raw);
      return parsed.data;
    },
    refetchInterval: refreshInterval || false,
  });
}

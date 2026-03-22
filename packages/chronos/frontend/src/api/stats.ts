import { useQuery } from '@tanstack/react-query';
import { apiFetch } from './client';
import { StatsResponseSchema, type StatsResponse } from '@/schemas/workflow';

export function useWorkflowStats() {
  return useQuery<StatsResponse>({
    queryKey: ['stats'],
    queryFn: async () => {
      const raw = await apiFetch<unknown>('/stats');
      return StatsResponseSchema.parse(raw);
    },
    refetchInterval: 5000,
  });
}

import { useQuery } from '@tanstack/react-query';
import { apiClient } from './client';
import type { TimePeriod } from '@/schemas/metrics';
import type {
  MetricsOverview,
  SlowRequestsResponse,
  SlowQueriesResponse,
  ExceptionCountsResponse,
  CacheRatioPoint,
  QueueThroughputPoint,
  ServerVitalsPoint,
} from '@/schemas/metrics';
import { useFiltersStore } from '@/stores/filters';

function usePeriod(): TimePeriod {
  return useFiltersStore((s) => s.timeRange);
}

export function useMetricsOverview() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'overview', period],
    queryFn: () =>
      apiClient<MetricsOverview>('/overview', { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useSlowRequests() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'slow-requests', period],
    queryFn: () =>
      apiClient<SlowRequestsResponse>('/slow-requests', { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useSlowQueries() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'slow-queries', period],
    queryFn: () =>
      apiClient<SlowQueriesResponse>('/slow-queries', { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useExceptionCounts() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'exception-counts', period],
    queryFn: () =>
      apiClient<ExceptionCountsResponse>('/exception-counts', { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useCacheRatio() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'cache-ratio', period],
    queryFn: () =>
      apiClient<{ data: CacheRatioPoint[] }>('/cache-ratio', { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useQueueThroughput() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'queue-throughput', period],
    queryFn: () =>
      apiClient<{ data: QueueThroughputPoint[] }>('/queue-throughput', { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useServerVitals() {
  const period = usePeriod();

  return useQuery({
    queryKey: ['nightwatch', 'metrics', 'servers', period],
    queryFn: () =>
      apiClient<{ data: ServerVitalsPoint[] }>('/servers', { params: { period } }),
    refetchInterval: 15_000,
  });
}

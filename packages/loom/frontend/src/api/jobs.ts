import { useQuery } from '@tanstack/react-query';
import { apiGet } from './client';
import {
  PaginatedJobsResponseSchema,
  PaginatedFailedJobsResponseSchema,
  JobDetailSchema,
  type Job,
  type FailedJob,
  type JobDetail,
  type PaginationMeta,
} from '@/schemas/job';
import { useFiltersStore } from '@/stores/filters';

interface PaginatedResult<T> {
  data: T[];
  meta: PaginationMeta;
}

export function useRecentJobs(page: number = 1, perPage: number = 25) {
  const queue = useFiltersStore((s) => s.selectedQueue);
  const search = useFiltersStore((s) => s.searchTerm);
  const refreshInterval = useFiltersStore((s) => s.refreshInterval);

  return useQuery<PaginatedResult<Job>>({
    queryKey: ['loom', 'jobs', 'recent', { page, perPage, queue, search }],
    queryFn: async () => {
      const raw = await apiGet<unknown>('/jobs/recent', {
        page,
        per_page: perPage,
        queue,
        search: search || null,
      });
      return PaginatedJobsResponseSchema.parse(raw);
    },
    refetchInterval: refreshInterval || false,
  });
}

export function useFailedJobs(page: number = 1, perPage: number = 25) {
  const queue = useFiltersStore((s) => s.selectedQueue);
  const search = useFiltersStore((s) => s.searchTerm);
  const refreshInterval = useFiltersStore((s) => s.refreshInterval);

  return useQuery<PaginatedResult<FailedJob>>({
    queryKey: ['loom', 'jobs', 'failed', { page, perPage, queue, search }],
    queryFn: async () => {
      const raw = await apiGet<unknown>('/jobs/failed', {
        page,
        per_page: perPage,
        queue,
        search: search || null,
      });
      return PaginatedFailedJobsResponseSchema.parse(raw);
    },
    refetchInterval: refreshInterval || false,
  });
}

export function useJobDetail(id: string | undefined) {
  return useQuery<JobDetail>({
    queryKey: ['loom', 'jobs', 'detail', id],
    queryFn: async () => {
      const raw = await apiGet<{ data: unknown }>(`/jobs/${id}`);
      return JobDetailSchema.parse(raw.data);
    },
    enabled: !!id,
  });
}

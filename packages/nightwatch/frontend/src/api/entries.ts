import { useQuery } from '@tanstack/react-query';
import { apiClient } from './client';
import type { BaseEntry, PaginatedResponse, StatusResponse, EntryType } from '@/schemas/entry';
import { StatusResponseSchema } from '@/schemas/entry';
import { useFiltersStore } from '@/stores/filters';

// ── Status ──

export function useStatus() {
  return useQuery({
    queryKey: ['nightwatch', 'status'],
    queryFn: async () => {
      const data = await apiClient<StatusResponse>('/status');
      return StatusResponseSchema.parse(data);
    },
    refetchInterval: 30_000,
  });
}

// ── Generic entries list ──

interface EntriesParams {
  type: EntryType;
  limit?: number;
  offset?: number;
  search?: string;
  status?: number | null;
  method?: string | null;
  level?: string | null;
  slow?: boolean;
  from?: string;
  to?: string;
}

export function useEntries(overrideType?: EntryType) {
  const filters = useFiltersStore();
  const type = overrideType ?? filters.entryType;

  return useQuery({
    queryKey: [
      'nightwatch',
      'entries',
      type,
      filters.page,
      filters.pageSize,
      filters.search,
      filters.statusFilter,
      filters.methodFilter,
      filters.levelFilter,
      filters.slowOnly,
      filters.timeRange,
    ],
    queryFn: () =>
      fetchEntries({
        type,
        limit: filters.pageSize,
        offset: filters.page * filters.pageSize,
        search: filters.search,
        status: filters.statusFilter,
        method: filters.methodFilter,
        level: filters.levelFilter,
        slow: filters.slowOnly,
      }),
    placeholderData: (prev) => prev,
  });
}

async function fetchEntries(params: EntriesParams): Promise<PaginatedResponse<BaseEntry>> {
  const { type, ...rest } = params;
  const endpointMap: Record<string, string> = {
    request: '/requests',
    query: '/queries',
    exception: '/exceptions',
    event: '/events',
    cache: '/cache',
    job: '/jobs',
    mail: '/mail',
    log: '/logs',
    model: '/models',
    gate: '/gates',
  };

  const endpoint = endpointMap[type] ?? `/${type}s`;

  return apiClient<PaginatedResponse<BaseEntry>>(endpoint, {
    params: {
      limit: rest.limit,
      offset: rest.offset,
      search: rest.search || undefined,
      status: rest.status ?? undefined,
      method: rest.method ?? undefined,
      level: rest.level ?? undefined,
      slow: rest.slow ? 'true' : undefined,
      from: rest.from,
      to: rest.to,
    },
  });
}

// ── Single entry detail ──

export function useEntry(type: EntryType, uuid: string | null) {
  const endpointMap: Record<string, string> = {
    request: '/requests',
    query: '/queries',
    exception: '/exceptions',
    event: '/events',
    cache: '/cache',
    job: '/jobs',
    mail: '/mail',
    log: '/logs',
    model: '/models',
    gate: '/gates',
  };

  const endpoint = endpointMap[type] ?? `/${type}s`;

  return useQuery({
    queryKey: ['nightwatch', 'entry', type, uuid],
    queryFn: () => apiClient<BaseEntry>(`${endpoint}/${uuid}`),
    enabled: !!uuid,
  });
}

// ── Batch view ──

export function useBatch(batchId: string | null) {
  return useQuery({
    queryKey: ['nightwatch', 'batch', batchId],
    queryFn: () => apiClient<PaginatedResponse<BaseEntry>>(`/batch/${batchId}`),
    enabled: !!batchId,
  });
}

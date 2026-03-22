"use client";

import { useQuery } from "@tanstack/react-query";
import { apiClient } from "./api";
import { useFiltersStore } from "./store";
import type {
  BaseEntry,
  PaginatedResponse,
  StatusResponse,
  EntryType,
  TimePeriod,
  MetricsOverview,
  SlowRequestsResponse,
  SlowQueriesResponse,
  ExceptionCountsResponse,
} from "./schemas";
import { StatusResponseSchema } from "./schemas";

// ── Status ──

export function useStatus() {
  return useQuery({
    queryKey: ["nightwatch", "status"],
    queryFn: async () => {
      const data = await apiClient<StatusResponse>("/status");
      return StatusResponseSchema.parse(data);
    },
    refetchInterval: 30_000,
  });
}

// ── Generic entries list ──

export function useEntries(overrideType?: EntryType) {
  const filters = useFiltersStore();
  const type = overrideType ?? filters.entryType;

  return useQuery({
    queryKey: [
      "nightwatch",
      "entries",
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

interface EntriesParams {
  type: EntryType;
  limit?: number;
  offset?: number;
  search?: string;
  status?: number | null;
  method?: string | null;
  level?: string | null;
  slow?: boolean;
}

async function fetchEntries(
  params: EntriesParams,
): Promise<PaginatedResponse<BaseEntry>> {
  const { type, ...rest } = params;
  const endpointMap: Record<string, string> = {
    request: "/requests",
    query: "/queries",
    exception: "/exceptions",
    event: "/events",
    cache: "/cache",
    job: "/jobs",
    mail: "/mail",
    log: "/logs",
    model: "/models",
    gate: "/gates",
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
      slow: rest.slow ? "true" : undefined,
    },
  });
}

// ── Metrics ──

function usePeriod(): TimePeriod {
  return useFiltersStore((s) => s.timeRange);
}

export function useMetricsOverview() {
  const period = usePeriod();

  return useQuery({
    queryKey: ["nightwatch", "metrics", "overview", period],
    queryFn: () =>
      apiClient<MetricsOverview>("/overview", { params: { period } }),
    refetchInterval: 15_000,
  });
}

export function useSlowRequests() {
  const period = usePeriod();

  return useQuery({
    queryKey: ["nightwatch", "metrics", "slow-requests", period],
    queryFn: () =>
      apiClient<SlowRequestsResponse>("/slow-requests", {
        params: { period },
      }),
    refetchInterval: 15_000,
  });
}

export function useSlowQueries() {
  const period = usePeriod();

  return useQuery({
    queryKey: ["nightwatch", "metrics", "slow-queries", period],
    queryFn: () =>
      apiClient<SlowQueriesResponse>("/slow-queries", {
        params: { period },
      }),
    refetchInterval: 15_000,
  });
}

export function useExceptionCounts() {
  const period = usePeriod();

  return useQuery({
    queryKey: ["nightwatch", "metrics", "exception-counts", period],
    queryFn: () =>
      apiClient<ExceptionCountsResponse>("/exception-counts", {
        params: { period },
      }),
    refetchInterval: 15_000,
  });
}

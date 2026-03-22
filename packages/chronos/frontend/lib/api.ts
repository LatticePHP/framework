import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch, buildQueryString } from "./api-client";
import {
  WorkflowListResponseSchema,
  WorkflowDetailResponseSchema,
  EventsListResponseSchema,
  StatsResponseSchema,
  SignalResponseSchema,
  RetryResponseSchema,
  CancelResponseSchema,
  type WorkflowListResponse,
  type WorkflowDetailResponse,
  type EventsListResponse,
  type StatsResponse,
} from "./schemas";

// --- Query param types ---

export interface WorkflowListParams {
  status?: string;
  type?: string;
  from?: string;
  to?: string;
  search?: string;
  sort?: string;
  order?: string;
  page?: number;
  per_page?: number;
}

export interface WorkflowEventsParams {
  page?: number;
  per_page?: number;
  event_type?: string;
  order?: string;
}

// --- Query hooks ---

export function useWorkflows(params: WorkflowListParams = {}) {
  return useQuery<WorkflowListResponse>({
    queryKey: ["workflows", params],
    queryFn: async () => {
      const qs = buildQueryString(
        params as Record<string, string | number | undefined>
      );
      const raw = await apiFetch<unknown>(`/workflows${qs}`);
      return WorkflowListResponseSchema.parse(raw);
    },
    refetchInterval: 5000,
  });
}

export function useWorkflow(id: string | undefined) {
  return useQuery<WorkflowDetailResponse>({
    queryKey: ["workflow", id],
    queryFn: async () => {
      const raw = await apiFetch<unknown>(`/workflows/${id}`);
      return WorkflowDetailResponseSchema.parse(raw);
    },
    enabled: !!id,
    refetchInterval: 3000,
  });
}

export function useWorkflowEvents(
  id: string | undefined,
  params: WorkflowEventsParams = {}
) {
  return useQuery<EventsListResponse>({
    queryKey: ["workflow-events", id, params],
    queryFn: async () => {
      const qs = buildQueryString(
        params as Record<string, string | number | undefined>
      );
      const raw = await apiFetch<unknown>(`/workflows/${id}/events${qs}`);
      return EventsListResponseSchema.parse(raw);
    },
    enabled: !!id,
    refetchInterval: 3000,
  });
}

export function useWorkflowStats() {
  return useQuery<StatsResponse>({
    queryKey: ["stats"],
    queryFn: async () => {
      const raw = await apiFetch<unknown>("/stats");
      return StatsResponseSchema.parse(raw);
    },
    refetchInterval: 5000,
  });
}

// --- Mutation hooks ---

export function useSignalWorkflow() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      id,
      signal,
      payload,
    }: {
      id: string;
      signal: string;
      payload?: unknown;
    }) => {
      const raw = await apiFetch<unknown>(`/workflows/${id}/signal`, {
        method: "POST",
        body: JSON.stringify({ signal, payload }),
      });
      return SignalResponseSchema.parse(raw);
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({
        queryKey: ["workflow", variables.id],
      });
      void queryClient.invalidateQueries({
        queryKey: ["workflow-events", variables.id],
      });
      void queryClient.invalidateQueries({ queryKey: ["workflows"] });
    },
  });
}

export function useRetryWorkflow() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id }: { id: string }) => {
      const raw = await apiFetch<unknown>(`/workflows/${id}/retry`, {
        method: "POST",
      });
      return RetryResponseSchema.parse(raw);
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({
        queryKey: ["workflow", variables.id],
      });
      void queryClient.invalidateQueries({ queryKey: ["workflows"] });
      void queryClient.invalidateQueries({ queryKey: ["stats"] });
    },
  });
}

export function useCancelWorkflow() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id }: { id: string }) => {
      const raw = await apiFetch<unknown>(`/workflows/${id}/cancel`, {
        method: "POST",
      });
      return CancelResponseSchema.parse(raw);
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({
        queryKey: ["workflow", variables.id],
      });
      void queryClient.invalidateQueries({ queryKey: ["workflows"] });
      void queryClient.invalidateQueries({ queryKey: ["stats"] });
    },
  });
}

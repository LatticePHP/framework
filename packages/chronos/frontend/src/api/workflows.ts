import { useQuery } from '@tanstack/react-query';
import { apiFetch, buildQueryString } from './client';
import {
  WorkflowListResponseSchema,
  WorkflowDetailResponseSchema,
  EventsListResponseSchema,
  type WorkflowListResponse,
  type WorkflowDetailResponse,
  type EventsListResponse,
} from '@/schemas/workflow';

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

export function useWorkflows(params: WorkflowListParams = {}) {
  return useQuery<WorkflowListResponse>({
    queryKey: ['workflows', params],
    queryFn: async () => {
      const qs = buildQueryString(params as Record<string, string | number | undefined>);
      const raw = await apiFetch<unknown>(`/workflows${qs}`);
      return WorkflowListResponseSchema.parse(raw);
    },
    refetchInterval: 5000,
  });
}

export function useWorkflow(id: string | undefined) {
  return useQuery<WorkflowDetailResponse>({
    queryKey: ['workflow', id],
    queryFn: async () => {
      const raw = await apiFetch<unknown>(`/workflows/${id}`);
      return WorkflowDetailResponseSchema.parse(raw);
    },
    enabled: !!id,
    refetchInterval: 3000,
  });
}

export interface WorkflowEventsParams {
  page?: number;
  per_page?: number;
  event_type?: string;
  order?: string;
}

export function useWorkflowEvents(
  id: string | undefined,
  params: WorkflowEventsParams = {},
) {
  return useQuery<EventsListResponse>({
    queryKey: ['workflow-events', id, params],
    queryFn: async () => {
      const qs = buildQueryString(params as Record<string, string | number | undefined>);
      const raw = await apiFetch<unknown>(`/workflows/${id}/events${qs}`);
      return EventsListResponseSchema.parse(raw);
    },
    enabled: !!id,
    refetchInterval: 3000,
  });
}

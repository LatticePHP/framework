import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from './client';
import {
  SignalResponseSchema,
  RetryResponseSchema,
  CancelResponseSchema,
} from '@/schemas/workflow';

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
        method: 'POST',
        body: JSON.stringify({ signal, payload }),
      });
      return SignalResponseSchema.parse(raw);
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: ['workflow', variables.id] });
      void queryClient.invalidateQueries({ queryKey: ['workflow-events', variables.id] });
      void queryClient.invalidateQueries({ queryKey: ['workflows'] });
    },
  });
}

export function useRetryWorkflow() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id }: { id: string }) => {
      const raw = await apiFetch<unknown>(`/workflows/${id}/retry`, {
        method: 'POST',
      });
      return RetryResponseSchema.parse(raw);
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: ['workflow', variables.id] });
      void queryClient.invalidateQueries({ queryKey: ['workflows'] });
      void queryClient.invalidateQueries({ queryKey: ['stats'] });
    },
  });
}

export function useCancelWorkflow() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id }: { id: string }) => {
      const raw = await apiFetch<unknown>(`/workflows/${id}/cancel`, {
        method: 'POST',
      });
      return CancelResponseSchema.parse(raw);
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: ['workflow', variables.id] });
      void queryClient.invalidateQueries({ queryKey: ['workflows'] });
      void queryClient.invalidateQueries({ queryKey: ['stats'] });
    },
  });
}

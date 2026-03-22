import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiPost, apiDelete } from './client';
import type { z } from 'zod';
import {
  RetryResponseSchema,
  RetryAllResponseSchema,
  DeleteResponseSchema,
} from '@/schemas/job';

type RetryResponse = z.infer<typeof RetryResponseSchema>;
type RetryAllResponse = z.infer<typeof RetryAllResponseSchema>;
type DeleteResponse = z.infer<typeof DeleteResponseSchema>;

export function useRetryJob() {
  const queryClient = useQueryClient();

  return useMutation<RetryResponse, Error, string>({
    mutationFn: async (jobId: string) => {
      const raw = await apiPost<unknown>(`/jobs/${jobId}/retry`);
      return RetryResponseSchema.parse(raw);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loom', 'jobs'] });
      void queryClient.invalidateQueries({ queryKey: ['loom', 'stats'] });
    },
  });
}

export function useRetryAll() {
  const queryClient = useQueryClient();

  return useMutation<RetryAllResponse, Error, void>({
    mutationFn: async () => {
      const raw = await apiPost<unknown>('/jobs/retry-all');
      return RetryAllResponseSchema.parse(raw);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loom', 'jobs'] });
      void queryClient.invalidateQueries({ queryKey: ['loom', 'stats'] });
    },
  });
}

export function useDeleteJob() {
  const queryClient = useQueryClient();

  return useMutation<DeleteResponse, Error, string>({
    mutationFn: async (jobId: string) => {
      const raw = await apiDelete<unknown>(`/jobs/${jobId}`);
      return DeleteResponseSchema.parse(raw);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loom', 'jobs'] });
      void queryClient.invalidateQueries({ queryKey: ['loom', 'stats'] });
    },
  });
}

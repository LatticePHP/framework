import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiPost } from "./client";
import type { Issue } from "@/schemas/issue";

interface StatusUpdateResponse {
  status: number;
  data: Issue;
}

export function useResolveIssue() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (issueId: string) =>
      apiPost<StatusUpdateResponse>(`/issues/${issueId}/resolve`, {
        status: "resolved",
      }),
    onSuccess: (_data, issueId) => {
      void queryClient.invalidateQueries({ queryKey: ["issue", issueId] });
      void queryClient.invalidateQueries({ queryKey: ["issues"] });
      void queryClient.invalidateQueries({ queryKey: ["stats"] });
    },
  });
}

export function useIgnoreIssue() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (issueId: string) =>
      apiPost<StatusUpdateResponse>(`/issues/${issueId}/resolve`, {
        status: "ignored",
      }),
    onSuccess: (_data, issueId) => {
      void queryClient.invalidateQueries({ queryKey: ["issue", issueId] });
      void queryClient.invalidateQueries({ queryKey: ["issues"] });
      void queryClient.invalidateQueries({ queryKey: ["stats"] });
    },
  });
}

export function useUnresolveIssue() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (issueId: string) =>
      apiPost<StatusUpdateResponse>(`/issues/${issueId}/resolve`, {
        status: "unresolved",
      }),
    onSuccess: (_data, issueId) => {
      void queryClient.invalidateQueries({ queryKey: ["issue", issueId] });
      void queryClient.invalidateQueries({ queryKey: ["issues"] });
      void queryClient.invalidateQueries({ queryKey: ["stats"] });
    },
  });
}

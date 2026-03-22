import { useQuery, keepPreviousData } from "@tanstack/react-query";
import { apiGet } from "./client";
import {
  IssueListResponseSchema,
  IssueDetailResponseSchema,
  type Issue,
  type ErrorEvent,
} from "@/schemas/issue";

export interface IssueFilters {
  status?: string;
  level?: string;
  environment?: string;
  platform?: string;
  search?: string;
  sort?: string;
  dir?: string;
  limit?: number;
  offset?: number;
}

interface IssueListResult {
  issues: Issue[];
  meta: { total: number; limit: number; offset: number };
}

async function fetchIssues(
  projectId: string,
  filters: IssueFilters,
): Promise<IssueListResult> {
  const raw = await apiGet<unknown>("/issues", {
    project_id: projectId,
    ...filters,
  });
  const parsed = IssueListResponseSchema.parse(raw);
  return { issues: parsed.data, meta: parsed.meta };
}

export function useIssues(
  projectId: string | undefined,
  filters: IssueFilters = {},
) {
  return useQuery({
    queryKey: ["issues", projectId, filters],
    queryFn: () => fetchIssues(projectId!, filters),
    enabled: !!projectId,
    placeholderData: keepPreviousData,
    staleTime: 10_000,
    refetchInterval: 30_000,
  });
}

interface IssueDetailResult {
  issue: Issue;
  sample_events: ErrorEvent[];
}

async function fetchIssue(id: string): Promise<IssueDetailResult> {
  const raw = await apiGet<unknown>(`/issues/${id}`);
  const parsed = IssueDetailResponseSchema.parse(raw);
  return parsed.data;
}

export function useIssue(id: string | undefined) {
  return useQuery({
    queryKey: ["issue", id],
    queryFn: () => fetchIssue(id!),
    enabled: !!id,
    staleTime: 15_000,
  });
}

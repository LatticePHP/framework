import type { Issue, Project, ErrorEvent, Stats, PaginatedMeta } from "./schemas";

const API_BASE = process.env.NEXT_PUBLIC_PRISM_API_URL ?? "/api/prism";

async function fetchApi<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: { "Content-Type": "application/json", ...init?.headers },
    ...init,
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.error ?? `API error ${res.status}`);
  }

  return res.json();
}

// ── Projects ──

export async function fetchProjects(): Promise<Project[]> {
  const res = await fetchApi<{ status: number; data: Project[] }>("/projects");
  return res.data;
}

// ── Issues ──

export interface IssueListParams {
  project_id: string;
  status?: string;
  level?: string;
  search?: string;
  sort?: string;
  dir?: string;
  limit?: number;
  offset?: number;
}

export interface IssueListResult {
  data: Issue[];
  meta: PaginatedMeta;
}

export async function fetchIssues(params: IssueListParams): Promise<IssueListResult> {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== "") query.set(k, String(v));
  });
  const res = await fetchApi<{ status: number; data: Issue[]; meta: PaginatedMeta }>(
    `/issues?${query.toString()}`
  );
  return { data: res.data, meta: res.meta };
}

// ── Issue detail ──

export interface IssueDetailResult {
  issue: Issue;
  sample_events: ErrorEvent[];
}

export async function fetchIssueDetail(id: string): Promise<IssueDetailResult> {
  const res = await fetchApi<{ status: number; data: IssueDetailResult }>(`/issues/${id}`);
  return res.data;
}

// ── Issue actions ──

export async function resolveIssue(id: string, status: string): Promise<Issue> {
  const res = await fetchApi<{ status: number; data: Issue }>(`/issues/${id}/resolve`, {
    method: "POST",
    body: JSON.stringify({ status }),
  });
  return res.data;
}

// ── Stats ──

export async function fetchStats(projectId?: string): Promise<Stats> {
  const query = projectId ? `?project_id=${encodeURIComponent(projectId)}` : "";
  const res = await fetchApi<{ status: number; data: Stats }>(`/stats${query}`);
  return res.data;
}

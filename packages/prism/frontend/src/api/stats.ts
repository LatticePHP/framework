import { useQuery } from "@tanstack/react-query";
import { apiGet } from "./client";
import { StatsResponseSchema, type Stats } from "@/schemas/issue";

async function fetchStats(projectId?: string): Promise<Stats> {
  const raw = await apiGet<unknown>("/stats", {
    project_id: projectId,
  });
  const parsed = StatsResponseSchema.parse(raw);
  return parsed.data;
}

export function useStats(projectId?: string) {
  return useQuery({
    queryKey: ["stats", projectId],
    queryFn: () => fetchStats(projectId),
    staleTime: 30_000,
    refetchInterval: 60_000,
  });
}

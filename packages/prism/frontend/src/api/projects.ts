import { useQuery } from "@tanstack/react-query";
import { apiGet } from "./client";
import { ProjectListResponseSchema, type Project } from "@/schemas/issue";

async function fetchProjects(): Promise<Project[]> {
  const raw = await apiGet<unknown>("/projects");
  const parsed = ProjectListResponseSchema.parse(raw);
  return parsed.data;
}

export function useProjects() {
  return useQuery({
    queryKey: ["projects"],
    queryFn: fetchProjects,
    staleTime: 60_000,
  });
}

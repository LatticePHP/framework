"use client";

import { useEffect, useState } from "react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { usePrismStore } from "@/lib/store";
import { fetchProjects } from "@/lib/api";
import type { Project } from "@/lib/schemas";

export default function ProjectSelector() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const { selectedProject, setSelectedProject } = usePrismStore();

  useEffect(() => {
    let cancelled = false;
    fetchProjects()
      .then((data) => {
        if (cancelled) return;
        setProjects(data);
        // Auto-select first project if none selected
        if (!selectedProject && data.length > 0) {
          setSelectedProject(data[0].id);
        }
      })
      .catch(() => {
        if (cancelled) return;
        // Use demo data when API is unreachable
        const demo: Project[] = [
          { id: "proj_1", name: "My App", slug: "my-app", created_at: new Date().toISOString() },
          { id: "proj_2", name: "API Service", slug: "api-service", created_at: new Date().toISOString() },
        ];
        setProjects(demo);
        if (!selectedProject) setSelectedProject(demo[0].id);
      })
      .finally(() => !cancelled && setLoading(false));
    return () => { cancelled = true; };
  }, [selectedProject, setSelectedProject]);

  if (loading) {
    return (
      <div className="h-10 w-full rounded-md border border-input bg-background animate-pulse" />
    );
  }

  return (
    <Select value={selectedProject ?? undefined} onValueChange={setSelectedProject}>
      <SelectTrigger className="w-full">
        <SelectValue placeholder="Select project" />
      </SelectTrigger>
      <SelectContent>
        {projects.map((project) => (
          <SelectItem key={project.id} value={project.id}>
            {project.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

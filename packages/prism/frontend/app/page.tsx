"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Gem, ArrowRight, FolderOpen } from "lucide-react";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { fetchProjects } from "@/lib/api";
import { usePrismStore } from "@/lib/store";
import type { Project } from "@/lib/schemas";

export default function ProjectsPage() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const setSelectedProject = usePrismStore((s) => s.setSelectedProject);

  useEffect(() => {
    let cancelled = false;
    fetchProjects()
      .then((data) => {
        if (!cancelled) setProjects(data);
      })
      .catch(() => {
        if (!cancelled) {
          setProjects([
            { id: "proj_1", name: "My App", slug: "my-app", created_at: "2025-12-01T10:00:00Z" },
            { id: "proj_2", name: "API Service", slug: "api-service", created_at: "2025-11-15T08:00:00Z" },
            { id: "proj_3", name: "Worker", slug: "worker", created_at: "2025-10-20T12:00:00Z" },
          ]);
        }
      })
      .finally(() => !cancelled && setLoading(false));
    return () => { cancelled = true; };
  }, []);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Gem className="h-6 w-6 text-primary" />
        <div>
          <h1 className="text-2xl font-bold">Projects</h1>
          <p className="text-sm text-muted-foreground">
            Select a project to view its error reports
          </p>
        </div>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="animate-pulse">
              <CardHeader>
                <div className="h-5 w-32 bg-muted rounded" />
                <div className="h-4 w-20 bg-muted rounded mt-2" />
              </CardHeader>
              <CardContent>
                <div className="h-9 w-24 bg-muted rounded" />
              </CardContent>
            </Card>
          ))}
        </div>
      ) : projects.length === 0 ? (
        <Card className="py-12">
          <CardContent className="text-center space-y-3">
            <FolderOpen className="h-12 w-12 mx-auto text-muted-foreground" />
            <p className="text-muted-foreground">No projects found.</p>
            <p className="text-sm text-muted-foreground">
              Start sending error events to create your first project.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {projects.map((project) => (
            <Card key={project.id} className="hover:border-primary/50 transition-colors">
              <CardHeader>
                <CardTitle className="text-lg">{project.name}</CardTitle>
                <CardDescription>
                  {project.slug ?? project.id}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Button
                  asChild
                  variant="outline"
                  size="sm"
                  onClick={() => setSelectedProject(project.id)}
                >
                  <Link href="/issues">
                    View Issues
                    <ArrowRight className="ml-2 h-4 w-4" />
                  </Link>
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

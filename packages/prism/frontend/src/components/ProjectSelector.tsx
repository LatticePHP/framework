import { Select, SelectItem, Skeleton } from "@nextui-org/react";
import { useProjects } from "@/api/projects";
import { useProjectStore } from "@/stores/project";

export function ProjectSelector() {
  const { data: projects, isLoading } = useProjects();
  const selectedProjectId = useProjectStore((s) => s.selectedProjectId);
  const setSelectedProjectId = useProjectStore((s) => s.setSelectedProjectId);

  if (isLoading) {
    return <Skeleton className="h-10 w-48 rounded-lg" />;
  }

  if (!projects || projects.length === 0) {
    return (
      <div className="text-sm text-default-400 px-2">No projects found</div>
    );
  }

  return (
    <Select
      label="Project"
      placeholder="Select a project"
      size="sm"
      variant="bordered"
      selectedKeys={selectedProjectId ? [selectedProjectId] : []}
      onChange={(e) => {
        const value = e.target.value;
        setSelectedProjectId(value || undefined);
      }}
      classNames={{
        base: "w-56",
        trigger: "h-10",
      }}
    >
      {projects.map((project) => (
        <SelectItem key={project.id} textValue={project.name}>
          <div className="flex flex-col">
            <span className="text-sm font-medium">{project.name}</span>
            {project.slug && (
              <span className="text-xs text-default-400">{project.slug}</span>
            )}
          </div>
        </SelectItem>
      ))}
    </Select>
  );
}

import {
  Card,
  CardBody,
  Skeleton,
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  useDisclosure,
} from "@nextui-org/react";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useProjects } from "@/api/projects";
import { useProjectStore } from "@/stores/project";
import { TimeAgo } from "@/components/TimeAgo";

export function ProjectsPage() {
  const { data: projects, isLoading, error } = useProjects();
  const setSelectedProjectId = useProjectStore((s) => s.setSelectedProjectId);
  const navigate = useNavigate();

  const { isOpen, onOpen, onClose } = useDisclosure();
  const [newProjectName, setNewProjectName] = useState("");

  const handleSelectProject = (projectId: string) => {
    setSelectedProjectId(projectId);
    navigate("/issues");
  };

  if (isLoading) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-6">Projects</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-32 rounded-xl" />
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <h2 className="text-2xl font-bold mb-4">Projects</h2>
        <Card>
          <CardBody className="text-center py-12">
            <p className="text-danger mb-2">Failed to load projects</p>
            <p className="text-sm text-default-400">
              {error instanceof Error ? error.message : "Unknown error"}
            </p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-2xl font-bold text-foreground">Projects</h2>
          <p className="text-sm text-default-400 mt-1">
            Manage your error tracking projects
          </p>
        </div>
        <Button color="primary" onPress={onOpen}>
          + Create Project
        </Button>
      </div>

      {/* Empty state */}
      {(!projects || projects.length === 0) && (
        <Card>
          <CardBody className="text-center py-16">
            <div className="text-4xl mb-4 text-default-300">
              { /* folder icon */ }
              <span className="inline-block">[ ]</span>
            </div>
            <h3 className="text-lg font-semibold mb-2">No projects yet</h3>
            <p className="text-sm text-default-400 mb-6 max-w-sm mx-auto">
              Create your first project to start tracking errors. You will get
              an API key to integrate with your application.
            </p>
            <Button color="primary" onPress={onOpen}>
              Create your first project
            </Button>
          </CardBody>
        </Card>
      )}

      {/* Project grid */}
      {projects && projects.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {projects.map((project) => (
            <Card
              key={project.id}
              isPressable
              onPress={() => handleSelectProject(project.id)}
              className="border border-default-200 hover:border-primary-300 transition-colors"
            >
              <CardBody className="p-5">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <h3 className="text-base font-semibold text-foreground">
                      {project.name}
                    </h3>
                    {project.slug && (
                      <p className="text-xs text-default-400 font-mono mt-0.5">
                        {project.slug}
                      </p>
                    )}
                  </div>
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-500/15 text-primary text-sm font-bold">
                    {project.name.charAt(0).toUpperCase()}
                  </div>
                </div>

                <div className="flex items-center justify-between text-xs text-default-400">
                  <span>
                    Created <TimeAgo date={project.created_at} />
                  </span>
                  <span className="text-primary text-xs font-medium">
                    View issues &rarr;
                  </span>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Create project modal */}
      <Modal isOpen={isOpen} onClose={onClose} placement="center">
        <ModalContent>
          <ModalHeader>Create New Project</ModalHeader>
          <ModalBody>
            <Input
              label="Project Name"
              placeholder="e.g., My API, Frontend App"
              value={newProjectName}
              onValueChange={setNewProjectName}
              variant="bordered"
              autoFocus
            />
            <p className="text-xs text-default-400">
              After creating the project, you will receive an API key to
              integrate with your application SDK.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>
              Cancel
            </Button>
            <Button
              color="primary"
              isDisabled={newProjectName.trim().length === 0}
              onPress={() => {
                // POST to /api/v1/projects would go here
                onClose();
                setNewProjectName("");
              }}
            >
              Create
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

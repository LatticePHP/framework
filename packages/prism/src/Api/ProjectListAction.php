<?php

declare(strict_types=1);

namespace Lattice\Prism\Api;

use Lattice\Prism\Database\Project;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/api/prism')]
final class ProjectListAction
{
    /**
     * In-memory project store for listing.
     *
     * @var array<string, Project>
     */
    private array $projects = [];

    /**
     * Register a project for listing.
     */
    public function addProject(Project $project): void
    {
        $this->projects[$project->id] = $project;
    }

    /**
     * GET /api/prism/projects
     *
     * @return array<string, mixed>
     */
    #[Get('/projects')]
    public function __invoke(): array
    {
        $list = [];

        foreach ($this->projects as $project) {
            $list[] = [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'created_at' => $project->createdAt,
            ];
        }

        return [
            'status' => 200,
            'data' => $list,
        ];
    }
}

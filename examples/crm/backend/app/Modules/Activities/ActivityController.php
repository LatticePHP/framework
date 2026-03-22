<?php

declare(strict_types=1);

namespace App\Modules\Activities;

use App\Models\Activity;
use App\Modules\Activities\Dto\CreateActivityDto;
use App\Modules\Activities\Dto\UpdateActivityDto;
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Principal;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Database\Filter\QueryFilter;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Observability\Log;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;

#[Controller('/api/activities')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ActivityController
{
    public function __construct(
        private readonly ActivityService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response
    {
        $filter = QueryFilter::fromRequest($request->query);
        $activities = Activity::filter($filter)
            ->with(['contact', 'deal'])
            ->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

        return ResponseFactory::paginated($activities, ActivityResource::class);
    }

    #[Post('/')]
    public function store(#[Body] CreateActivityDto $dto, #[CurrentUser] Principal $user): Response
    {
        $activity = $this->service->create($dto, $user);

        Log::info('Activity created', ['id' => $activity->id]);

        return ResponseFactory::created(
            ['data' => ActivityResource::make($activity)->toArray()],
        );
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $activity = Activity::with(['contact', 'deal', 'owner'])
            ->findOrFail($id);

        return ResponseFactory::json([
            'data' => ActivityResource::make($activity)->toArray(),
        ]);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateActivityDto $dto): Response
    {
        $activity = $this->service->update($id, $dto);

        return ResponseFactory::json([
            'data' => ActivityResource::make($activity)->toArray(),
        ]);
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        $this->service->delete($id);

        return ResponseFactory::noContent();
    }

    /**
     * Get upcoming activities (not completed, due in the future).
     */
    #[Get('/upcoming')]
    public function upcoming(Request $request): Response
    {
        $limit = (int) ($request->getQuery('limit') ?? 20);

        $activities = Activity::with(['contact', 'deal'])
            ->upcoming()
            ->limit($limit)
            ->get();

        return ResponseFactory::json([
            'data' => ActivityResource::collection($activities),
        ]);
    }

    /**
     * Get overdue activities (not completed, past due date).
     */
    #[Get('/overdue')]
    public function overdue(Request $request): Response
    {
        $limit = (int) ($request->getQuery('limit') ?? 20);

        $activities = Activity::with(['contact', 'deal'])
            ->overdue()
            ->limit($limit)
            ->get();

        return ResponseFactory::json([
            'data' => ActivityResource::collection($activities),
        ]);
    }

    /**
     * Mark an activity as completed.
     */
    #[Post('/:id/complete')]
    public function complete(#[Param] int $id): Response
    {
        $activity = $this->service->complete($id);

        return ResponseFactory::json([
            'data' => ActivityResource::make($activity)->toArray(),
        ]);
    }
}

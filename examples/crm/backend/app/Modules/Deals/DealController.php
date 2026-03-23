<?php

declare(strict_types=1);

namespace App\Modules\Deals;

use App\Models\Deal;
use App\Modules\Deals\Dto\CreateDealDto;
use App\Modules\Deals\Dto\UpdateDealDto;
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Principal;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Database\Crud\CrudService;
use Lattice\Http\Crud\CrudController;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Observability\Log;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;

#[Controller('/api/deals')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class DealController extends CrudController
{
    public function __construct(
        private readonly DealService $service,
    ) {}

    protected function service(): CrudService
    {
        return $this->service;
    }

    protected function resourceClass(): string
    {
        return DealResource::class;
    }

    protected function modelClass(): string
    {
        return Deal::class;
    }

    protected function indexRelations(): array
    {
        return ['contact', 'company'];
    }

    protected function showRelations(): array
    {
        return ['contact', 'company', 'activities', 'notes', 'owner'];
    }

    #[Post('/')]
    public function store(#[Body] CreateDealDto $dto, #[CurrentUser] Principal $user): Response
    {
        $deal = $this->service->create($dto, $user);

        Log::info('Deal created', ['id' => $deal->id]);

        return $this->storeResponse($deal);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateDealDto $dto): Response
    {
        return $this->updateResponse($this->service->update($id, $dto));
    }

    /**
     * Get pipeline view: deals grouped by stage.
     */
    #[Get('/pipeline')]
    public function pipeline(): Response
    {
        $pipeline = [];

        foreach (Deal::STAGES as $stage) {
            $deals = Deal::where('stage', $stage)
                ->with(['contact', 'company'])
                ->orderBy('value', 'desc')
                ->get();

            $pipeline[] = [
                'stage' => $stage,
                'count' => $deals->count(),
                'total_value' => (float) $deals->sum('value'),
                'deals' => DealResource::collection($deals),
            ];
        }

        return ResponseFactory::json(['data' => $pipeline]);
    }

    /**
     * Move a deal to a new stage.
     */
    #[Post('/:id/stage')]
    public function moveStage(#[Param] int $id, #[Body] array $body): Response
    {
        $stage = $body['stage'] ?? null;
        abort_unless($stage !== null, 422, 'Stage is required');

        $lostReason = $body['lost_reason'] ?? null;
        $deal = $this->service->moveStage($id, $stage, $lostReason);

        return ResponseFactory::json([
            'data' => DealResource::make($deal)->toArray(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Notes;

use App\Models\Note;
use App\Modules\Notes\Dto\CreateNoteDto;
use App\Modules\Notes\Dto\UpdateNoteDto;
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

#[Controller('/api/notes')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class NoteController
{
    public function __construct(
        private readonly NoteService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response
    {
        $filter = QueryFilter::fromRequest($request->query);
        $notes = Note::filter($filter)
            ->with(['author'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

        return ResponseFactory::paginated($notes, NoteResource::class);
    }

    #[Post('/')]
    public function store(#[Body] CreateNoteDto $dto, #[CurrentUser] Principal $user): Response
    {
        $note = $this->service->create($dto, $user);

        Log::info('Note created', ['id' => $note->id]);

        return ResponseFactory::created(
            ['data' => NoteResource::make($note)->toArray()],
        );
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $note = Note::with(['author', 'notable'])->findOrFail($id);

        return ResponseFactory::json([
            'data' => NoteResource::make($note)->toArray(),
        ]);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateNoteDto $dto): Response
    {
        $note = $this->service->update($id, $dto);

        return ResponseFactory::json([
            'data' => NoteResource::make($note)->toArray(),
        ]);
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        $this->service->delete($id);

        return ResponseFactory::noContent();
    }

    /**
     * Get notes for a specific entity (polymorphic).
     */
    #[Get('/for/:type/:entityId')]
    public function forEntity(#[Param] string $type, #[Param] int $entityId): Response
    {
        $notableType = match ($type) {
            'contacts' => \App\Models\Contact::class,
            'companies' => \App\Models\Company::class,
            'deals' => \App\Models\Deal::class,
            default => abort(422, "Invalid entity type: {$type}"),
        };

        $notes = Note::where('notable_type', $notableType)
            ->where('notable_id', $entityId)
            ->with(['author'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseFactory::json([
            'data' => NoteResource::collection($notes),
        ]);
    }
}

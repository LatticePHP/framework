<?php

declare(strict_types=1);

namespace App\Modules\Notes;

use App\Models\Note;
use App\Modules\Notes\Dto\CreateNoteDto;
use App\Modules\Notes\Dto\UpdateNoteDto;
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

#[Controller('/api/notes')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class NoteController extends CrudController
{
    public function __construct(
        private readonly NoteService $service,
    ) {}

    protected function service(): CrudService
    {
        return $this->service;
    }

    protected function resourceClass(): string
    {
        return NoteResource::class;
    }

    protected function modelClass(): string
    {
        return Note::class;
    }

    protected function indexRelations(): array
    {
        return ['author'];
    }

    protected function showRelations(): array
    {
        return ['author', 'notable'];
    }

    #[Post('/')]
    public function store(#[Body] CreateNoteDto $dto, #[CurrentUser] Principal $user): Response
    {
        $note = $this->service->create($dto, $user);

        Log::info('Note created', ['id' => $note->id]);

        return $this->storeResponse($note);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateNoteDto $dto): Response
    {
        return $this->updateResponse($this->service->update($id, $dto));
    }

    /**
     * Get notes for a specific entity (polymorphic).
     */
    #[Get('/for/:type/:entityId')]
    public function forEntity(#[Param] string $type, #[Param] int $entityId): Response
    {
        $notableType = Note::resolveNotableClass($type);

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

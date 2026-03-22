<?php

declare(strict_types=1);

namespace App\Modules\Notes;

use App\Models\Note;
use App\Modules\Notes\Dto\CreateNoteDto;
use App\Modules\Notes\Dto\UpdateNoteDto;
use Lattice\Auth\Principal;
use Lattice\Observability\Log;

final class NoteService
{
    /** @var array<string, class-string> Maps API-friendly names to model classes */
    private const array NOTABLE_TYPE_MAP = [
        'contacts' => \App\Models\Contact::class,
        'companies' => \App\Models\Company::class,
        'deals' => \App\Models\Deal::class,
    ];

    /**
     * Create a new note.
     */
    public function create(CreateNoteDto $dto, Principal $user): Note
    {
        $notableType = self::NOTABLE_TYPE_MAP[$dto->notable_type] ?? $dto->notable_type;

        $note = Note::create([
            'content' => $dto->body,
            'notable_type' => $notableType,
            'notable_id' => $dto->notable_id,
            'author_id' => (int) $user->getId(),
            'is_pinned' => $dto->is_pinned ?? false,
        ]);

        Log::info('Note created', [
            'id' => $note->id,
            'notable_type' => $dto->notable_type,
            'notable_id' => $dto->notable_id,
            'user_id' => $user->getId(),
        ]);

        return $note;
    }

    /**
     * Update an existing note.
     */
    public function update(int $id, UpdateNoteDto $dto): Note
    {
        $note = Note::findOrFail($id);

        $data = array_filter([
            'content' => $dto->body,
            'is_pinned' => $dto->is_pinned,
        ], fn (mixed $value): bool => $value !== null);

        $note->update($data);

        Log::info('Note updated', ['id' => $note->id]);

        return $note->fresh();
    }

    /**
     * Delete a note (soft delete).
     */
    public function delete(int $id): void
    {
        $note = Note::findOrFail($id);
        $note->delete();

        Log::info('Note deleted', ['id' => $id]);
    }
}

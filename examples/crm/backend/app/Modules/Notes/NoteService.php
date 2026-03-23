<?php

declare(strict_types=1);

namespace App\Modules\Notes;

use App\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Lattice\Auth\Principal;
use Lattice\Database\Crud\CrudService;

/**
 * @extends CrudService<Note>
 */
final class NoteService extends CrudService
{
    protected function model(): string
    {
        return Note::class;
    }

    protected function ownerField(): string
    {
        return 'author_id';
    }

    /**
     * Map 'body' -> 'content' and resolve polymorphic notable_type.
     *
     * @param array<string, mixed> &$data
     */
    protected function beforeCreate(array &$data, Principal $user): void
    {
        if (array_key_exists('body', $data)) {
            $data['content'] = $data['body'];
            unset($data['body']);
        }

        if (isset($data['notable_type'])) {
            $data['notable_type'] = Note::resolveNotableClass($data['notable_type']);
        }
    }

    /**
     * Map 'body' -> 'content' on update.
     *
     * @param array<string, mixed> &$data
     */
    protected function beforeUpdate(Model $model, array &$data): void
    {
        if (array_key_exists('body', $data)) {
            $data['content'] = $data['body'];
            unset($data['body']);
        }
    }
}

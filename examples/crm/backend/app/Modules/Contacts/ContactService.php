<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Models\Contact;
use App\Modules\Contacts\Dto\CreateContactDto;
use App\Modules\Contacts\Dto\UpdateContactDto;
use Lattice\Auth\Principal;
use Lattice\Observability\Log;

final class ContactService
{
    /**
     * Create a new contact.
     */
    public function create(CreateContactDto $dto, Principal $user): Contact
    {
        $contact = Contact::create([
            'first_name' => $dto->first_name,
            'last_name' => $dto->last_name,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'company_id' => $dto->company_id,
            'title' => $dto->title,
            'status' => $dto->status,
            'source' => $dto->source,
            'owner_id' => (int) $user->getId(),
            'tags' => $dto->tags ?? [],
        ]);

        Log::info('Contact created', [
            'id' => $contact->id,
            'email' => $contact->email,
            'user_id' => $user->getId(),
        ]);

        return $contact;
    }

    /**
     * Update an existing contact.
     */
    public function update(int $id, UpdateContactDto $dto): Contact
    {
        $contact = Contact::findOrFail($id);

        $data = array_filter([
            'first_name' => $dto->first_name,
            'last_name' => $dto->last_name,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'company_id' => $dto->company_id,
            'title' => $dto->title,
            'status' => $dto->status,
            'source' => $dto->source,
            'tags' => $dto->tags,
        ], fn (mixed $value): bool => $value !== null);

        $contact->update($data);

        Log::info('Contact updated', ['id' => $contact->id]);

        return $contact->fresh();
    }

    /**
     * Delete a contact (soft delete).
     */
    public function delete(int $id): void
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        Log::info('Contact deleted', ['id' => $id]);
    }
}

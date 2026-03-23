<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Models\Contact;
use App\Modules\Contacts\Dto\CreateContactDto;
use App\Modules\Contacts\Dto\UpdateContactDto;
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Principal;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Database\Crud\CrudService;
use Lattice\Http\Crud\CrudController;
use Lattice\Http\Request;
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

#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController extends CrudController
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    protected function service(): CrudService
    {
        return $this->service;
    }

    protected function resourceClass(): string
    {
        return ContactResource::class;
    }

    protected function modelClass(): string
    {
        return Contact::class;
    }

    protected function indexRelations(): array
    {
        return ['company'];
    }

    protected function showRelations(): array
    {
        return ['company', 'deals', 'activities', 'notes', 'owner'];
    }

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
    {
        $contact = $this->service->create($dto, $user);

        Log::info('Contact created', ['id' => $contact->id]);

        return $this->storeResponse($contact);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateContactDto $dto): Response
    {
        return $this->updateResponse($this->service->update($id, $dto));
    }

    /**
     * Search contacts by query string.
     */
    #[Get('/search')]
    public function search(Request $request): Response
    {
        $query = $request->getQuery('q') ?? '';
        abort_if($query === '', 422, 'Search query is required');

        $contacts = Contact::search($query)
            ->with(['company'])
            ->paginate(15);

        return ResponseFactory::paginated($contacts, ContactResource::class);
    }
}

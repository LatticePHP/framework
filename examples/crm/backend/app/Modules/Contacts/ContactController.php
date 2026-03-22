<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Models\Contact;
use App\Modules\Contacts\Dto\CreateContactDto;
use App\Modules\Contacts\Dto\UpdateContactDto;
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

#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response
    {
        $filter = QueryFilter::fromRequest($request->query);
        $contacts = Contact::filter($filter)
            ->with(['company'])
            ->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

        return ResponseFactory::paginated($contacts, ContactResource::class);
    }

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
    {
        $contact = $this->service->create($dto, $user);

        Log::info('Contact created', ['id' => $contact->id]);

        return ResponseFactory::created(
            ['data' => ContactResource::make($contact)->toArray()],
        );
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $contact = Contact::with(['company', 'deals', 'activities', 'notes', 'owner'])
            ->findOrFail($id);

        return ResponseFactory::json([
            'data' => ContactResource::make($contact)->toArray(),
        ]);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateContactDto $dto): Response
    {
        $contact = $this->service->update($id, $dto);

        return ResponseFactory::json([
            'data' => ContactResource::make($contact)->toArray(),
        ]);
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        $this->service->delete($id);

        return ResponseFactory::noContent();
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

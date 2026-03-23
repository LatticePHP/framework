<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Models\Company;
use App\Modules\Companies\Dto\CreateCompanyDto;
use App\Modules\Companies\Dto\UpdateCompanyDto;
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

#[Controller('/api/companies')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class CompanyController extends CrudController
{
    public function __construct(
        private readonly CompanyService $service,
    ) {}

    protected function service(): CrudService
    {
        return $this->service;
    }

    protected function resourceClass(): string
    {
        return CompanyResource::class;
    }

    protected function modelClass(): string
    {
        return Company::class;
    }

    protected function showRelations(): array
    {
        return ['contacts', 'deals', 'notes', 'owner'];
    }

    #[Post('/')]
    public function store(#[Body] CreateCompanyDto $dto, #[CurrentUser] Principal $user): Response
    {
        $company = $this->service->create($dto, $user);

        Log::info('Company created', ['id' => $company->id]);

        return $this->storeResponse($company);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateCompanyDto $dto): Response
    {
        return $this->updateResponse($this->service->update($id, $dto));
    }

    /**
     * Search companies by query string.
     */
    #[Get('/search')]
    public function search(Request $request): Response
    {
        $query = $request->getQuery('q') ?? '';
        abort_if($query === '', 422, 'Search query is required');

        $companies = Company::search($query)
            ->paginate(15);

        return ResponseFactory::paginated($companies, CompanyResource::class);
    }
}

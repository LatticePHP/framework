<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Models\Company;
use App\Modules\Companies\Dto\CreateCompanyDto;
use App\Modules\Companies\Dto\UpdateCompanyDto;
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

#[Controller('/api/companies')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class CompanyController
{
    public function __construct(
        private readonly CompanyService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response
    {
        $filter = QueryFilter::fromRequest($request->query);
        $companies = Company::filter($filter)
            ->withCount(['contacts', 'deals'])
            ->paginate($filter->getPerPage(), ['*'], 'page', $filter->getPage());

        return ResponseFactory::paginated($companies, CompanyResource::class);
    }

    #[Post('/')]
    public function store(#[Body] CreateCompanyDto $dto, #[CurrentUser] Principal $user): Response
    {
        $company = $this->service->create($dto, $user);

        Log::info('Company created', ['id' => $company->id]);

        return ResponseFactory::created(
            ['data' => CompanyResource::make($company)->toArray()],
        );
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $company = Company::with(['contacts', 'deals', 'notes', 'owner'])
            ->findOrFail($id);

        return ResponseFactory::json([
            'data' => CompanyResource::make($company)->toArray(),
        ]);
    }

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateCompanyDto $dto): Response
    {
        $company = $this->service->update($id, $dto);

        return ResponseFactory::json([
            'data' => CompanyResource::make($company)->toArray(),
        ]);
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        $this->service->delete($id);

        return ResponseFactory::noContent();
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

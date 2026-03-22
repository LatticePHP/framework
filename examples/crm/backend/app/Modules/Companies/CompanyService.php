<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Models\Company;
use App\Modules\Companies\Dto\CreateCompanyDto;
use App\Modules\Companies\Dto\UpdateCompanyDto;
use Lattice\Auth\Principal;
use Lattice\Observability\Log;

final class CompanyService
{
    /**
     * Create a new company.
     */
    public function create(CreateCompanyDto $dto, Principal $user): Company
    {
        $company = Company::create([
            'name' => $dto->name,
            'domain' => $dto->domain,
            'industry' => $dto->industry,
            'size' => $dto->size,
            'phone' => $dto->phone,
            'email' => $dto->email,
            'address' => $dto->address,
            'city' => $dto->city,
            'state' => $dto->state,
            'country' => $dto->country,
            'website' => $dto->website,
            'owner_id' => (int) $user->getId(),
        ]);

        Log::info('Company created', [
            'id' => $company->id,
            'name' => $company->name,
            'user_id' => $user->getId(),
        ]);

        return $company;
    }

    /**
     * Update an existing company.
     */
    public function update(int $id, UpdateCompanyDto $dto): Company
    {
        $company = Company::findOrFail($id);

        $data = array_filter([
            'name' => $dto->name,
            'domain' => $dto->domain,
            'industry' => $dto->industry,
            'size' => $dto->size,
            'phone' => $dto->phone,
            'email' => $dto->email,
            'address' => $dto->address,
            'city' => $dto->city,
            'state' => $dto->state,
            'country' => $dto->country,
            'website' => $dto->website,
        ], fn (mixed $value): bool => $value !== null);

        $company->update($data);

        Log::info('Company updated', ['id' => $company->id]);

        return $company->fresh();
    }

    /**
     * Delete a company (soft delete).
     */
    public function delete(int $id): void
    {
        $company = Company::findOrFail($id);
        $company->delete();

        Log::info('Company deleted', ['id' => $id]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Models\Company;
use Lattice\Database\Crud\CrudService;

/**
 * @extends CrudService<Company>
 */
final class CompanyService extends CrudService
{
    protected function model(): string
    {
        return Company::class;
    }
}

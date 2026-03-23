<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Models\Contact;
use Lattice\Database\Crud\CrudService;

/**
 * @extends CrudService<Contact>
 */
final class ContactService extends CrudService
{
    protected function model(): string
    {
        return Contact::class;
    }
}

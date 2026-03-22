<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Database\Model;

class TestContact extends Model
{
    protected $table = 'test_contacts';

    protected $fillable = ['name', 'email', 'status'];

    public $timestamps = false;
}

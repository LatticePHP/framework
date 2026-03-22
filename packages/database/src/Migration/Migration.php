<?php

declare(strict_types=1);

namespace Lattice\Database\Migration;

use Lattice\Database\Schema\SchemaBuilder;

abstract class Migration
{
    abstract public function up(SchemaBuilder $schema): void;

    abstract public function down(SchemaBuilder $schema): void;
}

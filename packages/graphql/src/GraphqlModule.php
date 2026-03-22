<?php

declare(strict_types=1);

namespace Lattice\GraphQL;

use Lattice\Compiler\Attributes\Module;
use Lattice\GraphQL\Execution\GraphqlController;
use Lattice\GraphQL\Execution\QueryExecutor;
use Lattice\GraphQL\Schema\SchemaBuilder;
use Lattice\GraphQL\Schema\TypeRegistry;

#[Module(
    providers: [GraphqlServiceProvider::class],
    controllers: [GraphqlController::class],
    exports: [SchemaBuilder::class, TypeRegistry::class, QueryExecutor::class],
)]
final class GraphqlModule
{
}

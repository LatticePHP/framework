<?php

declare(strict_types=1);

namespace Lattice\GraphQL;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Core\Support\ServiceProvider;
use Lattice\GraphQL\Execution\ErrorFormatter;
use Lattice\GraphQL\Execution\GraphqlController;
use Lattice\GraphQL\Execution\QueryExecutor;
use Lattice\GraphQL\Schema\FieldResolver;
use Lattice\GraphQL\Schema\ResolverDiscovery;
use Lattice\GraphQL\Schema\SchemaBuilder;
use Lattice\GraphQL\Schema\TypeRegistry;

final class GraphqlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $container = $this->container;

        $container->singleton(TypeRegistry::class, function (): TypeRegistry {
            return new TypeRegistry();
        });

        $container->singleton(FieldResolver::class, function (): FieldResolver {
            return new FieldResolver();
        });

        $container->singleton(ResolverDiscovery::class, function (): ResolverDiscovery {
            return new ResolverDiscovery();
        });

        $container->singleton(SchemaBuilder::class, function () use ($container): SchemaBuilder {
            return new SchemaBuilder(
                $container->make(TypeRegistry::class),
                $container->make(FieldResolver::class),
                $container->make(ResolverDiscovery::class),
            );
        });

        $container->singleton(ErrorFormatter::class, function (): ErrorFormatter {
            $debug = (bool) ($_ENV['APP_DEBUG'] ?? false);
            return new ErrorFormatter($debug);
        });

        $container->singleton(QueryExecutor::class, function () use ($container): QueryExecutor {
            return new QueryExecutor(
                $container->make(SchemaBuilder::class),
                $container->make(ErrorFormatter::class),
                $container->make(FieldResolver::class),
            );
        });

        $container->singleton(GraphqlController::class, function () use ($container): GraphqlController {
            return new GraphqlController(
                $container->make(QueryExecutor::class),
            );
        });
    }
}

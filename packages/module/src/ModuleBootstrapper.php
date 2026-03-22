<?php

declare(strict_types=1);

namespace Lattice\Module;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Contracts\Module\ModuleLifecycleInterface;
use Lattice\Core\Support\ServiceProvider;
use Lattice\Module\Exception\UnsatisfiedImportException;
use Lattice\Module\Provider\ProviderRegistry;

final class ModuleBootstrapper
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
    ) {}

    /**
     * Boot all modules in topological order.
     *
     * ServiceProvider subclasses get special treatment:
     * 1. All register() methods are called first (across all modules)
     * 2. Then all boot() methods are called (across all modules)
     *
     * This ensures services registered in one provider are available
     * in boot() of another provider.
     *
     * @return list<string> The boot order (module class names)
     */
    public function boot(ModuleRegistry $registry, ContainerInterface $container): array
    {
        $this->validateImports($registry);

        $bootOrder = $registry->getBootOrder();

        /** @var list<ServiceProvider> $serviceProviderInstances */
        $serviceProviderInstances = [];

        foreach ($bootOrder as $moduleName) {
            $definition = $registry->get($moduleName);

            // Register providers
            $providers = $definition->getProviders();
            if ($providers !== []) {
                $instantiated = $this->registerProviders($moduleName, $providers, $container);
                array_push($serviceProviderInstances, ...$instantiated);
            }

            // Register factory bindings for dynamic modules
            if ($definition instanceof DynamicModuleDefinition) {
                foreach ($definition->getFactoryBindings() as $abstract => $factory) {
                    $this->providerRegistry->registerFactory($moduleName, $abstract, $factory, $container);
                }
            }

            // Mark exports
            $exports = $definition->getExports();
            if ($exports !== []) {
                $this->providerRegistry->markExported($moduleName, $exports);
            }

            // Call lifecycle hook if module implements it
            $this->callLifecycleInit($moduleName, $container);
        }

        // Phase 2: boot all ServiceProvider instances after all registrations
        foreach ($serviceProviderInstances as $provider) {
            $provider->boot();
        }

        return $bootOrder;
    }

    /**
     * Register providers for a module, instantiating ServiceProvider subclasses.
     *
     * @param string $moduleName
     * @param array<class-string> $providerClasses
     * @param ContainerInterface $container
     * @return list<ServiceProvider> Instantiated ServiceProvider instances
     */
    private function registerProviders(
        string $moduleName,
        array $providerClasses,
        ContainerInterface $container,
    ): array {
        $serviceProviders = [];

        foreach ($providerClasses as $providerClass) {
            if ($this->isServiceProvider($providerClass)) {
                /** @var ServiceProvider $instance */
                $instance = new $providerClass($container);
                $instance->register();
                $this->providerRegistry->registerValue($moduleName, $providerClass, $instance, $container);
                $serviceProviders[] = $instance;
            } else {
                // Plain class binding (not a ServiceProvider)
                $this->providerRegistry->registerProviders($moduleName, [$providerClass], $container);
            }
        }

        return $serviceProviders;
    }

    /**
     * Check if a class extends ServiceProvider.
     */
    private function isServiceProvider(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, ServiceProvider::class);
    }

    /**
     * Shutdown all modules, calling onModuleDestroy in reverse boot order.
     */
    public function shutdown(ModuleRegistry $registry, ContainerInterface $container): void
    {
        $bootOrder = $registry->getBootOrder();
        $reversed = array_reverse($bootOrder);

        foreach ($reversed as $moduleName) {
            $this->callLifecycleDestroy($moduleName, $container);
        }
    }

    /**
     * Validate that all imported modules are registered.
     *
     * @throws UnsatisfiedImportException
     */
    private function validateImports(ModuleRegistry $registry): void
    {
        foreach ($registry->all() as $moduleName => $definition) {
            foreach ($definition->getImports() as $import) {
                if (!$registry->has($import)) {
                    throw UnsatisfiedImportException::forModule($moduleName, $import);
                }
            }
        }
    }

    private function callLifecycleInit(string $moduleName, ContainerInterface $container): void
    {
        if (!$container->has($moduleName)) {
            return;
        }

        $instance = $container->make($moduleName);

        if ($instance instanceof ModuleLifecycleInterface) {
            $instance->onModuleInit();
        }
    }

    private function callLifecycleDestroy(string $moduleName, ContainerInterface $container): void
    {
        if (!$container->has($moduleName)) {
            return;
        }

        $instance = $container->make($moduleName);

        if ($instance instanceof ModuleLifecycleInterface) {
            $instance->onModuleDestroy();
        }
    }
}

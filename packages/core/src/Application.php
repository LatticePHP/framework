<?php

declare(strict_types=1);

namespace Lattice\Core;

use Lattice\Core\Config\ConfigRepository;
use Lattice\Core\Container\Container;
use Lattice\Core\Container\IlluminateContainer;
use Lattice\Core\Environment\EnvLoader;
use Lattice\Core\Lifecycle\LifecycleManager;
use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Http\Exception\HttpException;
use Lattice\Http\ExceptionHandler;
use Lattice\Http\ExceptionRenderer;
use Lattice\Http\HttpKernel;
use Lattice\Http\ParameterResolver;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Routing\RouteCollector;
use Lattice\Routing\RouteDiscoverer;
use Lattice\Routing\Router;

final class Application
{
    private static ?self $instance = null;

    private readonly ContainerInterface $container;
    private readonly ConfigRepository $config;
    private readonly LifecycleManager $lifecycle;

    private bool $booted = false;

    /** @var Router|null Cached router for long-running workers */
    private ?Router $cachedRouter = null;

    /** @var list<class-string> Controllers discovered from modules */
    private array $controllers = [];

    /** @var array<string, ModuleDefinitionInterface> Registered module definitions */
    private array $moduleDefinitions = [];

    /**
     * @param list<class-string> $modules
     * @param array<string, bool> $transports
     * @param ContainerInterface|null $container Custom container instance (defaults to IlluminateContainer)
     * @param array<class-string> $globalGuards Global guard class names for HTTP pipeline
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $modules = [],
        private readonly array $transports = [],
        private readonly bool $observability = false,
        ?ContainerInterface $container = null,
        private readonly array $globalGuards = [],
    ) {
        $this->container = $container ?? self::createDefaultContainer();
        $this->config = new ConfigRepository();
        $this->lifecycle = new LifecycleManager();

        $this->registerCoreBindings();

        self::$instance = $this;
    }

    /**
     * Get the globally available instance of the application.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been instantiated.');
        }

        return self::$instance;
    }

    /**
     * Clear the cached instance (primarily for testing).
     */
    public static function clearInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the current application environment.
     */
    public function environment(): string
    {
        return $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
    }

    /**
     * Determine if the application is in the production environment.
     */
    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    /**
     * Determine if the application is in the local environment.
     */
    public function isLocal(): bool
    {
        return $this->environment() === 'local';
    }

    /**
     * Determine if the application is in the testing environment.
     */
    public function isTesting(): bool
    {
        return $this->environment() === 'testing';
    }

    /**
     * Determine if the application is in debug mode.
     */
    public function isDebug(): bool
    {
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';
        return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Create the default container.
     *
     * Uses IlluminateContainer (backed by illuminate/container) for production.
     * Falls back to the lightweight Container if Illuminate is unavailable.
     */
    private static function createDefaultContainer(): ContainerInterface
    {
        if (class_exists(\Illuminate\Container\Container::class)) {
            return new IlluminateContainer();
        }

        return new Container();
    }

    public static function configure(string $basePath): ApplicationBuilder
    {
        return new ApplicationBuilder($basePath);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return list<class-string>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    public function hasTransport(string $transport): bool
    {
        return $this->transports[$transport] ?? false;
    }

    public function hasObservability(): bool
    {
        return $this->observability;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getConfig(): ConfigRepository
    {
        return $this->config;
    }

    public function getLifecycle(): LifecycleManager
    {
        return $this->lifecycle;
    }

    /**
     * Get all controllers discovered from modules.
     *
     * @return list<class-string>
     */
    public function getControllers(): array
    {
        return $this->controllers;
    }

    /**
     * Get registered module definitions.
     *
     * @return array<string, ModuleDefinitionInterface>
     */
    public function getModuleDefinitions(): array
    {
        return $this->moduleDefinitions;
    }

    /**
     * Handle an incoming HTTP request through the full lifecycle.
     *
     * Boots the application if not already booted, builds the HTTP kernel
     * with discovered routes, and returns the response.
     */
    public function handleRequest(Request $request): Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        try {
            $kernel = $this->getHttpKernel();
            return $kernel->handle($request);
        } catch (\Throwable $e) {
            return $this->getExceptionRenderer()->render($e);
        }
    }

    /**
     * Get the exception renderer, resolving from container or creating a default.
     */
    private function getExceptionRenderer(): ExceptionRenderer
    {
        if ($this->container->has(ExceptionRenderer::class)) {
            /** @var ExceptionRenderer */
            return $this->container->get(ExceptionRenderer::class);
        }

        $logger = null;
        if ($this->container->has(\Psr\Log\LoggerInterface::class)) {
            /** @var \Psr\Log\LoggerInterface */
            $logger = $this->container->get(\Psr\Log\LoggerInterface::class);
        }

        return new ExceptionRenderer(
            debug: $this->isDebug(),
            logger: $logger,
        );
    }

    /**
     * Build the HTTP kernel with routes discovered from modules.
     *
     * The Router is built once and cached for long-running workers (RoadRunner).
     * In non-production environments, the router is rebuilt when controllers change.
     */
    private function getHttpKernel(): HttpKernel
    {
        if ($this->cachedRouter === null) {
            $router = new Router();
            $discoverer = new RouteDiscoverer();
            $discoverer->discoverAndRegister($this->controllers, $router);
            $this->cachedRouter = $router;

            // Store in container for other services (e.g., URL generation)
            $this->container->instance(Router::class, $router);
        }

        return new HttpKernel(
            router: $this->cachedRouter,
            container: $this->container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
            globalGuardClasses: $this->globalGuards,
        );
    }

    /**
     * Boot the application through its lifecycle phases.
     */
    public function boot(): void
    {
        $this->lifecycle->executePreBoot();
        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->bootModules();
        $this->lifecycle->executeBoot();
        $this->lifecycle->executeReady();
        $this->booted = true;
    }

    /**
     * Terminate the application.
     */
    public function terminate(): void
    {
        $this->lifecycle->executeTerminate();
    }

    private function registerCoreBindings(): void
    {
        $this->container->instance(self::class, $this);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ConfigRepository::class, $this->config);
        $this->container->instance('config', $this->config);
        $this->container->instance(LifecycleManager::class, $this->lifecycle);
    }

    private function loadEnvironment(): void
    {
        $envFile = $this->basePath . '/.env';

        if (file_exists($envFile)) {
            $vars = EnvLoader::loadFile($envFile);

            foreach ($vars as $key => $value) {
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    private function loadConfiguration(): void
    {
        $configPath = $this->basePath . '/config';

        if (is_dir($configPath)) {
            $this->config->loadFromDirectory($configPath);
        }
    }

    /**
     * Scan module classes for #[Module] attribute, build module graph,
     * register providers, and collect controllers.
     */
    private function bootModules(): void
    {
        if ($this->modules === []) {
            return;
        }

        // Try to use the full module system if available
        if ($this->bootWithModuleSystem()) {
            return;
        }

        // Fallback: direct attribute scanning and provider registration
        $this->bootWithAttributeScanning();
    }

    /**
     * Boot modules using the lattice/module package (ModuleRegistry + ModuleBootstrapper).
     */
    private function bootWithModuleSystem(): bool
    {
        if (!class_exists(\Lattice\Module\ModuleRegistry::class)
            || !class_exists(\Lattice\Module\ModuleBootstrapper::class)
            || !class_exists(\Lattice\Module\Provider\ProviderRegistry::class)
            || !class_exists(\Lattice\Module\ModuleDefinition::class)
        ) {
            return false;
        }

        $moduleAttribute = $this->resolveModuleAttributeClass();

        if ($moduleAttribute === null) {
            return false;
        }

        $registry = new \Lattice\Module\ModuleRegistry();
        $providerRegistry = new \Lattice\Module\Provider\ProviderRegistry();

        // Recursively discover all modules (including imports)
        $allModules = $this->discoverAllModules($this->modules, $moduleAttribute);

        foreach ($allModules as $moduleClass) {
            $definition = $this->buildModuleDefinition($moduleClass, $moduleAttribute);

            if ($definition !== null) {
                $registry->register($moduleClass, $definition);
                $this->moduleDefinitions[$moduleClass] = $definition;
            }
        }

        $bootstrapper = new \Lattice\Module\ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        // Collect controllers from all modules
        foreach ($this->moduleDefinitions as $definition) {
            foreach ($definition->getControllers() as $controller) {
                $this->controllers[] = $controller;
            }
        }

        // Store references for later use
        $this->container->instance(\Lattice\Module\ModuleRegistry::class, $registry);
        $this->container->instance(\Lattice\Module\Provider\ProviderRegistry::class, $providerRegistry);

        return true;
    }

    /**
     * Fallback: scan attributes directly and register providers without the full module system.
     */
    private function bootWithAttributeScanning(): void
    {
        $moduleAttribute = $this->resolveModuleAttributeClass();

        foreach ($this->modules as $moduleClass) {
            if (!class_exists($moduleClass)) {
                continue;
            }

            $ref = new \ReflectionClass($moduleClass);
            $attrs = $ref->getAttributes($moduleAttribute ?? \Lattice\Compiler\Attributes\Module::class);

            if ($attrs === []) {
                // Also check Lattice\Module\Attribute\Module
                $attrs = $ref->getAttributes(\Lattice\Module\Attribute\Module::class);
            }

            if ($attrs === []) {
                continue;
            }

            $attr = $attrs[0]->newInstance();

            // Register providers
            foreach ($attr->providers as $providerClass) {
                $this->container->bind($providerClass, $providerClass);
            }

            // Register exports
            foreach ($attr->exports as $exportClass) {
                $this->container->bind($exportClass, $exportClass);
            }

            // Collect controllers
            foreach ($attr->controllers as $controller) {
                $this->controllers[] = $controller;
            }

            $this->moduleDefinitions[$moduleClass] = new class($attr->imports, $attr->providers, $attr->controllers, $attr->exports) implements ModuleDefinitionInterface {
                public function __construct(
                    private readonly array $imports,
                    private readonly array $providers,
                    private readonly array $controllers,
                    private readonly array $exports,
                ) {}

                public function getImports(): array { return $this->imports; }
                public function getProviders(): array { return $this->providers; }
                public function getControllers(): array { return $this->controllers; }
                public function getExports(): array { return $this->exports; }
            };
        }
    }

    /**
     * Resolve which Module attribute class is available.
     */
    private function resolveModuleAttributeClass(): ?string
    {
        if (class_exists(\Lattice\Module\Attribute\Module::class)) {
            return \Lattice\Module\Attribute\Module::class;
        }

        if (class_exists(\Lattice\Compiler\Attributes\Module::class)) {
            return \Lattice\Compiler\Attributes\Module::class;
        }

        return null;
    }

    /**
     * Recursively discover all modules by following imports.
     */
    private function discoverAllModules(array $rootModules, string $attributeClass, array &$discovered = []): array
    {
        foreach ($rootModules as $moduleClass) {
            if (in_array($moduleClass, $discovered, true)) {
                continue;
            }
            if (!class_exists($moduleClass)) {
                continue;
            }
            $discovered[] = $moduleClass;

            // Read the #[Module] attribute to find imports (check both Module attribute classes)
            $ref = new \ReflectionClass($moduleClass);
            $moduleAttrClasses = [$attributeClass];
            if (class_exists(\Lattice\Compiler\Attributes\Module::class)) {
                $moduleAttrClasses[] = \Lattice\Compiler\Attributes\Module::class;
            }
            if (class_exists(\Lattice\Module\Attribute\Module::class)) {
                $moduleAttrClasses[] = \Lattice\Module\Attribute\Module::class;
            }
            $moduleAttrClasses = array_unique($moduleAttrClasses);

            foreach ($moduleAttrClasses as $attrClass) {
                $attrs = $ref->getAttributes($attrClass);
                if (!empty($attrs)) {
                    $attr = $attrs[0]->newInstance();
                    $imports = $attr->imports ?? [];
                    if (!empty($imports)) {
                        $this->discoverAllModules($imports, $attributeClass, $discovered);
                    }
                    break;
                }
            }
        }
        return $discovered;
    }

    /**
     * Build a ModuleDefinition from a class with a #[Module] attribute.
     */
    private function buildModuleDefinition(string $moduleClass, string $attributeClass): ?ModuleDefinitionInterface
    {
        if (!class_exists($moduleClass)) {
            return null;
        }

        $ref = new \ReflectionClass($moduleClass);

        // Check multiple Module attribute classes
        $moduleAttrClasses = [$attributeClass];
        if (class_exists(\Lattice\Compiler\Attributes\Module::class)) {
            $moduleAttrClasses[] = \Lattice\Compiler\Attributes\Module::class;
        }
        if (class_exists(\Lattice\Module\Attribute\Module::class)) {
            $moduleAttrClasses[] = \Lattice\Module\Attribute\Module::class;
        }

        foreach (array_unique($moduleAttrClasses) as $attrClass) {
            $attrs = $ref->getAttributes($attrClass);
            if (!empty($attrs)) {
                $attr = $attrs[0]->newInstance();
                // Build ModuleDefinition directly from attribute properties
                return new \Lattice\Module\ModuleDefinition(
                    imports: $attr->imports ?? [],
                    providers: $attr->providers ?? [],
                    controllers: $attr->controllers ?? [],
                    exports: $attr->exports ?? [],
                );
            }
        }

        return null;
    }
}

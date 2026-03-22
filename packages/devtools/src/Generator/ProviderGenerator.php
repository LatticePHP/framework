<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class ProviderGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'provider';
    }

    public function getDescription(): string
    {
        return 'Creates a service/provider class';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Provider name is required');
        $module = $options['module'] ?? 'App';

        return [
            new GeneratedFile(
                path: 'app/Providers/' . $name . 'Provider.php',
                content: $this->template->render($this->providerTemplate(), [
                    'namespace' => $module . '\\Providers',
                    'className' => $name . 'Provider',
                ]),
            ),
        ];
    }

    private function providerTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{namespace}};

use Lattice\Compiler\Attributes\Injectable;

#[Injectable]
final class {{className}}
{
    public function register(): void
    {
        // Register bindings and services
    }

    public function boot(): void
    {
        // Boot logic
    }
}
PHP;
    }
}

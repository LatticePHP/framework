<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class ModuleGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'module';
    }

    public function getDescription(): string
    {
        return 'Creates a complete module skeleton with DDD directory structure';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Module name is required');
        $path = $options['path'] ?? 'src/Modules/' . $name;

        $files = [];

        // Module class
        $files[] = new GeneratedFile(
            path: $path . '/' . $name . 'Module.php',
            content: $this->template->render($this->moduleTemplate(), [
                'moduleName' => $name,
                'className' => $name . 'Module',
            ]),
        );

        // DDD directory structure
        foreach (['Domain', 'Application', 'Infrastructure', 'Interfaces'] as $dir) {
            $files[] = new GeneratedFile(
                path: $path . '/' . $dir . '/.gitkeep',
                content: '',
            );
        }

        return $files;
    }

    private function moduleTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\{{moduleName}};

use Lattice\Compiler\Attributes\Module;

#[Module(
    imports: [],
    providers: [],
    controllers: [],
    exports: [],
)]
final class {{className}} {}
PHP;
    }
}

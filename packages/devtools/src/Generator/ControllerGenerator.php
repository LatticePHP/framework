<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class ControllerGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'controller';
    }

    public function getDescription(): string
    {
        return 'Creates a controller with route attributes and optional test file';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Controller name is required');
        $module = $options['module'] ?? 'App';
        /** @var string[] $methods */
        $methods = $options['methods'] ?? ['index'];

        $files = [];

        $resourceName = lcfirst($name);
        $methodsCode = $this->generateMethods($methods, $resourceName);
        $useStatements = $this->generateUseStatements($methods);

        $files[] = new GeneratedFile(
            path: 'app/Http/' . $name . 'Controller.php',
            content: $this->template->render($this->controllerTemplate(), [
                'namespace' => $module . '\\Http',
                'className' => $name . 'Controller',
                'routePrefix' => '/' . $this->pluralize($resourceName),
                'methods' => $methodsCode,
                'useStatements' => $useStatements,
            ]),
        );

        // Test file
        $files[] = new GeneratedFile(
            path: 'tests/Http/' . $name . 'ControllerTest.php',
            content: $this->template->render($this->testTemplate(), [
                'namespace' => $module . '\\Tests\\Http',
                'className' => $name . 'ControllerTest',
                'controllerClass' => $name . 'Controller',
            ]),
        );

        return $files;
    }

    /**
     * @param string[] $methods
     */
    private function generateMethods(array $methods, string $resource): string
    {
        $code = [];

        foreach ($methods as $method) {
            $code[] = match ($method) {
                'index' => $this->indexMethod(),
                'show' => $this->showMethod($resource),
                'create' => $this->createMethod($resource),
                'update' => $this->updateMethod($resource),
                'delete' => $this->deleteMethod($resource),
                default => '',
            };
        }

        return implode("\n\n", array_filter($code));
    }

    /**
     * @param string[] $methods
     */
    private function generateUseStatements(array $methods): string
    {
        $uses = ['use Lattice\Routing\Attributes\Controller;'];

        $attrMap = [
            'index' => 'Get',
            'show' => 'Get',
            'create' => 'Post',
            'update' => 'Put',
            'delete' => 'Delete',
        ];

        $needed = [];
        foreach ($methods as $method) {
            if (isset($attrMap[$method])) {
                $needed[$attrMap[$method]] = true;
            }
        }

        foreach (array_keys($needed) as $attr) {
            $uses[] = 'use Lattice\Routing\Attributes\\' . $attr . ';';
        }

        sort($uses);

        return implode("\n", $uses);
    }

    private function indexMethod(): string
    {
        return <<<'PHP'
    #[Get('/')]
    public function index(): array
    {
        return ['data' => []];
    }
PHP;
    }

    private function showMethod(string $resource): string
    {
        return <<<PHP
    #[Get('/{id}')]
    public function show(string \$id): array
    {
        return ['data' => ['id' => \$id]];
    }
PHP;
    }

    private function createMethod(string $resource): string
    {
        return <<<'PHP'
    #[Post('/')]
    public function create(array $body): array
    {
        return ['data' => $body, 'status' => 'created'];
    }
PHP;
    }

    private function updateMethod(string $resource): string
    {
        return <<<PHP
    #[Put('/{id}')]
    public function update(string \$id, array \$body): array
    {
        return ['data' => array_merge(['id' => \$id], \$body)];
    }
PHP;
    }

    private function deleteMethod(string $resource): string
    {
        return <<<PHP
    #[Delete('/{id}')]
    public function delete(string \$id): array
    {
        return ['status' => 'deleted', 'id' => \$id];
    }
PHP;
    }

    private function controllerTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{namespace}};

{{useStatements}}

#[Controller('{{routePrefix}}')]
final class {{className}}
{
{{methods}}
}
PHP;
    }

    private function testTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace {{namespace}};

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class {{className}} extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->markTestIncomplete('Implement {{controllerClass}} tests');
    }
}
PHP;
    }

    private function pluralize(string $word): string
    {
        if (str_ends_with($word, 's')) {
            return $word . 'es';
        }
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }
        return $word . 's';
    }
}

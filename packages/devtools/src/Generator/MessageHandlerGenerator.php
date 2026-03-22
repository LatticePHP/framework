<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class MessageHandlerGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'message-handler';
    }

    public function getDescription(): string
    {
        return 'Creates event/command message handler classes';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Handler name is required');
        /** @var string[] $patterns */
        $patterns = $options['patterns'] ?? [];

        $handlers = $this->generateHandlerMethods($patterns);

        return [
            new GeneratedFile(
                path: 'app/Handlers/' . $name . 'Handler.php',
                content: $this->template->render($this->handlerTemplate(), [
                    'className' => $name . 'Handler',
                    'handlers' => $handlers,
                ]),
            ),
        ];
    }

    /**
     * @param string[] $patterns
     */
    private function generateHandlerMethods(array $patterns): string
    {
        if ($patterns === []) {
            return <<<'PHP'
    #[EventPattern('example.event')]
    public function handleExample(mixed $data): void
    {
        // Handle event
    }
PHP;
        }

        $methods = [];
        foreach ($patterns as $pattern) {
            $methodName = 'handle' . str_replace(['.', '-', '_'], '', ucwords($pattern, '.-_'));
            $isCommand = str_contains($pattern, 'command') || str_contains($pattern, 'cmd');
            $attribute = $isCommand ? 'CommandPattern' : 'EventPattern';

            $methods[] = <<<PHP
    #[{$attribute}('{$pattern}')]
    public function {$methodName}(mixed \$data): void
    {
        // Handle {$pattern}
    }
PHP;
        }

        return implode("\n\n", $methods);
    }

    private function handlerTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Handlers;

use Lattice\Microservices\Attributes\CommandPattern;
use Lattice\Microservices\Attributes\EventPattern;
use Lattice\Microservices\Attributes\MessageController;

#[MessageController]
final class {{className}}
{
{{handlers}}
}
PHP;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class PolicyGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'policy';
    }

    public function getDescription(): string
    {
        return 'Creates an authorization policy implementing PolicyInterface';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Policy name is required');
        $resource = $options['resource'] ?? $name;
        /** @var string[] $abilities */
        $abilities = $options['abilities'] ?? ['view', 'create', 'update', 'delete'];

        $abilityCases = $this->generateAbilityCases($abilities);

        return [
            new GeneratedFile(
                path: 'app/Policies/' . $name . 'Policy.php',
                content: $this->template->render($this->policyTemplate(), [
                    'className' => $name . 'Policy',
                    'resource' => $resource,
                    'abilityCases' => $abilityCases,
                ]),
            ),
        ];
    }

    /**
     * @param string[] $abilities
     */
    private function generateAbilityCases(array $abilities): string
    {
        $cases = [];
        foreach ($abilities as $ability) {
            $cases[] = "            '{$ability}' => \$this->{$ability}(\$principal, \$subject),";
        }
        return implode("\n", $cases);
    }

    private function policyTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Policies;

use Lattice\Contracts\Auth\PolicyInterface;
use Lattice\Contracts\Context\PrincipalInterface;

final class {{className}} implements PolicyInterface
{
    public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
    {
        return match ($ability) {
{{abilityCases}}
            default => false,
        };
    }

    private function view(PrincipalInterface $principal, mixed $subject): bool
    {
        return true;
    }

    private function create(PrincipalInterface $principal, mixed $subject): bool
    {
        return true;
    }

    private function update(PrincipalInterface $principal, mixed $subject): bool
    {
        return true;
    }

    private function delete(PrincipalInterface $principal, mixed $subject): bool
    {
        return true;
    }
}
PHP;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakePolicyCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:policy');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new authorization policy class')
            ->addArgument('name', InputArgument::REQUIRED, 'The policy name (e.g., User)')
            ->addOption('abilities', null, InputOption::VALUE_OPTIONAL, 'Comma-separated abilities', 'view,create,update,delete')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'The module to create the policy in');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $abilitiesStr = (string) $input->getOption('abilities');
        $module = $input->getOption('module');
        $style = new LatticeStyle($output);

        $style->banner();

        $className = str_ends_with($name, 'Policy') ? $name : $name . 'Policy';
        $namespace = $module ? "App\\Modules\\{$module}\\Policies" : 'App\\Policies';
        $basePath = $module ? "src/Modules/{$module}/Policies" : 'app/Policies';

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $abilities = array_map('trim', explode(',', $abilitiesStr));
        $abilityCases = $this->generateAbilityCases($abilities);
        $abilityMethods = $this->generateAbilityMethods($abilities);

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Lattice\\Contracts\\Auth\\PolicyInterface;
            use Lattice\\Contracts\\Context\\PrincipalInterface;

            final class {$className} implements PolicyInterface
            {
                public function can(PrincipalInterface \$principal, string \$ability, mixed \$subject = null): bool
                {
                    return match (\$ability) {
            {$abilityCases}
                        default => false,
                    };
                }
            {$abilityMethods}
            }
            PHP;

        $filePath = $basePath . '/' . $className . '.php';
        file_put_contents($filePath, $content);

        $style->success("Policy <fg=white>{$className}</> created at <fg=gray>{$filePath}</>");
        $style->info("Abilities: " . implode(', ', $abilities));
        $style->newLine();

        return Command::SUCCESS;
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

    /**
     * @param string[] $abilities
     */
    private function generateAbilityMethods(array $abilities): string
    {
        $methods = [];
        foreach ($abilities as $ability) {
            $methods[] = <<<PHP

                private function {$ability}(PrincipalInterface \$principal, mixed \$subject): bool
                {
                    return true;
                }
            PHP;
        }
        return implode("\n", $methods);
    }
}

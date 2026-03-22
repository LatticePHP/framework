<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Console;

use Lattice\Catalyst\Skills\SkillLoader;
use Lattice\Catalyst\Skills\SkillRegistry;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CatalystSkillsCommand extends Command
{
    private ?SkillRegistry $registry;
    private string $basePath;

    public function __construct(?SkillRegistry $registry = null, string $basePath = '')
    {
        parent::__construct('catalyst:skills');
        $this->registry = $registry;
        $this->basePath = $basePath;
    }

    public function setRegistry(SkillRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List all available Catalyst skills')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = $this->registry;

        if ($registry === null) {
            $loader = new SkillLoader();
            $basePath = $this->basePath !== '' ? $this->basePath : (string) getcwd();
            $loader->loadAll($basePath);
            $registry = $loader->getRegistry();
        }

        if ($input->getOption('json')) {
            $skills = [];

            foreach ($registry->all() as $name => $skill) {
                $skills[] = [
                    'name' => $skill['name'],
                    'description' => $skill['description'],
                    'triggers' => $skill['triggers'],
                    'source' => $skill['source'],
                ];
            }

            $output->writeln((string) json_encode($skills, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Available Skills');
        $style->newLine();

        $skills = $registry->all();

        if ($skills === []) {
            $style->info('No skills discovered');
            $style->newLine();

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($skills as $skill) {
            $rows[] = [
                $skill['name'],
                $skill['description'],
                implode(', ', $skill['triggers']),
                $skill['source'],
            ];
        }

        $style->table(
            ['Name', 'Description', 'Triggers', 'Source'],
            $rows,
        );

        $style->newLine();
        $style->info('Total skills: ' . count($skills));
        $style->newLine();

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Catalyst;

use Lattice\Catalyst\Console\CatalystInstallCommand;
use Lattice\Catalyst\Console\CatalystMcpCommand;
use Lattice\Catalyst\Console\CatalystSkillsCommand;
use Lattice\Catalyst\Console\CatalystUpdateCommand;
use Lattice\Catalyst\Guidelines\GuidelineGenerator;
use Lattice\Catalyst\Guidelines\GuidelineRegistry;
use Lattice\Catalyst\Mcp\McpServer;
use Lattice\Catalyst\Skills\SkillLoader;
use Lattice\Catalyst\Skills\SkillRegistry;
use Lattice\Contracts\Container\ContainerInterface;

final class CatalystServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->bind(GuidelineRegistry::class, GuidelineRegistry::class);
        $container->bind(GuidelineGenerator::class, GuidelineGenerator::class);
        $container->bind(SkillLoader::class, SkillLoader::class);
        $container->bind(SkillRegistry::class, SkillRegistry::class);
        $container->bind(McpServer::class, McpServer::class);
    }

    /**
     * @return list<\Symfony\Component\Console\Command\Command>
     */
    public function commands(): array
    {
        return [
            new CatalystInstallCommand(),
            new CatalystUpdateCommand(),
            new CatalystMcpCommand(),
            new CatalystSkillsCommand(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lattice\Ripple\Console;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Ripple\Server\WebSocketServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Starts the Ripple WebSocket server.
 *
 * Usage: php lattice ripple:serve --host=0.0.0.0 --port=6001
 */
final class RippleServeCommand extends Command
{
    public function __construct()
    {
        parent::__construct('ripple:serve');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start the Ripple WebSocket server')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to listen on', '0.0.0.0')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to listen on', '6001')
            ->addOption('max-connections', null, InputOption::VALUE_OPTIONAL, 'Maximum number of connections', '10000')
            ->addOption('heartbeat', null, InputOption::VALUE_OPTIONAL, 'Heartbeat interval in seconds', '25');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $maxConnections = (int) $input->getOption('max-connections');
        $heartbeat = (int) $input->getOption('heartbeat');

        $style = new LatticeStyle($output);
        $style->banner();

        $style->header('Ripple WebSocket Server');
        $style->newLine();
        $style->keyValue('Host', $host);
        $style->keyValue('Port', (string) $port);
        $style->keyValue('PID', (string) getmypid());
        $style->keyValue('Max connections', (string) $maxConnections);
        $style->keyValue('Heartbeat interval', $heartbeat . 's');
        $style->keyValue('Event loop', 'ext-sockets');
        $style->newLine();

        $style->info("Listening on <fg=white>ws://{$host}:{$port}</>");
        $style->info('Press <fg=yellow>Ctrl+C</> to stop');
        $style->newLine();

        $server = new WebSocketServer(
            host: $host,
            port: $port,
            maxConnections: $maxConnections,
            heartbeatInterval: $heartbeat,
        );

        $server->onConnect(function ($connection) use ($style): void {
            $style->success(sprintf(
                'Connected: %s (IP: %s:%d)',
                $connection->id,
                $connection->remoteIp,
                $connection->remotePort,
            ));
        });

        $server->onDisconnect(function ($connection) use ($style): void {
            $style->info(sprintf(
                'Disconnected: %s (IP: %s:%d)',
                $connection->id,
                $connection->remoteIp,
                $connection->remotePort,
            ));
        });

        $server->onError(function (string $error) use ($style): void {
            $style->error($error);
        });

        $server->start();

        return Command::SUCCESS;
    }
}

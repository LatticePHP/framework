<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\ConnectionConfig;
use Lattice\Database\ConnectionInterface;
use Lattice\Database\ConnectionManager;
use Lattice\Database\SqliteConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionManagerTest extends TestCase
{
    #[Test]
    public function it_adds_and_retrieves_a_connection(): void
    {
        $manager = new ConnectionManager();
        $config = ConnectionConfig::sqlite(':memory:');

        $manager->addConnection('default', $config);

        $connection = $manager->connection('default');
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    #[Test]
    public function it_returns_default_connection_when_no_name_given(): void
    {
        $manager = new ConnectionManager();
        $config = ConnectionConfig::sqlite(':memory:');

        $manager->addConnection('default', $config);

        $connection = $manager->connection();
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    #[Test]
    public function it_returns_the_default_connection_name(): void
    {
        $manager = new ConnectionManager();
        $this->assertSame('default', $manager->getDefaultConnectionName());
    }

    #[Test]
    public function it_throws_for_unknown_connection(): void
    {
        $manager = new ConnectionManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->connection('nonexistent');
    }

    #[Test]
    public function it_reuses_the_same_connection_instance(): void
    {
        $manager = new ConnectionManager();
        $config = ConnectionConfig::sqlite(':memory:');
        $manager->addConnection('default', $config);

        $conn1 = $manager->connection('default');
        $conn2 = $manager->connection('default');

        $this->assertSame($conn1, $conn2);
    }

    #[Test]
    public function it_creates_sqlite_connection_for_sqlite_driver(): void
    {
        $manager = new ConnectionManager();
        $config = ConnectionConfig::sqlite(':memory:');
        $manager->addConnection('default', $config);

        $connection = $manager->connection('default');
        $this->assertInstanceOf(SqliteConnection::class, $connection);
    }
}

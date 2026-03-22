<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Database\ConnectionInterface;
use Lattice\Database\Seeder\Seeder;
use Lattice\Database\Seeder\SeederRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SeederTest extends TestCase
{
    #[Test]
    public function test_seeder_run_is_called(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $seeder = new class ($connection) extends Seeder {
            public bool $ran = false;

            public function run(): void
            {
                $this->ran = true;
            }
        };

        $seeder->run();

        $this->assertTrue($seeder->ran);
    }

    #[Test]
    public function test_seeder_has_connection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $seeder = new class ($connection) extends Seeder {
            public function run(): void
            {
                // no-op
            }

            public function getConn(): ConnectionInterface
            {
                return $this->connection;
            }
        };

        $this->assertSame($connection, $seeder->getConn());
    }

    #[Test]
    public function test_seeder_runner_run(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $runner = new SeederRunner($connection);

        // Use a concrete tracker via a global to verify the seeder ran
        SeederTestTracker::$ran = false;

        $runner->run(TrackingSeeder::class);

        $this->assertTrue(SeederTestTracker::$ran);
    }

    #[Test]
    public function test_seeder_runner_run_all(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $runner = new SeederRunner($connection);

        SeederTestTracker::$ran = false;
        SeederTestTracker2::$ran = false;

        $runner->runAll([TrackingSeeder::class, TrackingSeeder2::class]);

        $this->assertTrue(SeederTestTracker::$ran);
        $this->assertTrue(SeederTestTracker2::$ran);
    }

    #[Test]
    public function test_seeder_call_runs_child_seeders(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        SeederTestTracker::$ran = false;

        $parent = new ParentSeeder($connection);
        $parent->run();

        $this->assertTrue(SeederTestTracker::$ran);
    }
}

/**
 * @internal Test helpers
 */
final class SeederTestTracker
{
    public static bool $ran = false;
}

final class SeederTestTracker2
{
    public static bool $ran = false;
}

final class TrackingSeeder extends Seeder
{
    public function run(): void
    {
        SeederTestTracker::$ran = true;
    }
}

final class TrackingSeeder2 extends Seeder
{
    public function run(): void
    {
        SeederTestTracker2::$ran = true;
    }
}

final class ParentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TrackingSeeder::class);
    }
}

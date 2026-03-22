<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Config\ConfigRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigRepositoryTest extends TestCase
{
    #[Test]
    public function get_returns_value_by_key(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'Lattice']]);

        $this->assertSame(['name' => 'Lattice'], $config->get('app'));
    }

    #[Test]
    public function get_returns_default_for_missing_key(): void
    {
        $config = new ConfigRepository([]);

        $this->assertSame('fallback', $config->get('missing', 'fallback'));
    }

    #[Test]
    public function get_supports_dot_notation(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => ['host' => '127.0.0.1'],
                ],
            ],
        ]);

        $this->assertSame('mysql', $config->get('database.default'));
        $this->assertSame('127.0.0.1', $config->get('database.connections.mysql.host'));
    }

    #[Test]
    public function get_returns_default_for_missing_nested_key(): void
    {
        $config = new ConfigRepository(['database' => ['default' => 'mysql']]);

        $this->assertNull($config->get('database.missing'));
        $this->assertSame('fallback', $config->get('database.missing', 'fallback'));
    }

    #[Test]
    public function set_stores_value_by_key(): void
    {
        $config = new ConfigRepository([]);

        $config->set('app.name', 'Lattice');

        $this->assertSame('Lattice', $config->get('app.name'));
    }

    #[Test]
    public function set_with_dot_notation_creates_nested_structure(): void
    {
        $config = new ConfigRepository([]);

        $config->set('database.connections.pgsql.host', 'localhost');

        $this->assertSame('localhost', $config->get('database.connections.pgsql.host'));
    }

    #[Test]
    public function has_returns_true_for_existing_key(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'Lattice']]);

        $this->assertTrue($config->has('app'));
        $this->assertTrue($config->has('app.name'));
        $this->assertFalse($config->has('missing'));
        $this->assertFalse($config->has('app.missing'));
    }

    #[Test]
    public function all_returns_entire_config(): void
    {
        $items = ['app' => ['name' => 'Lattice'], 'debug' => true];
        $config = new ConfigRepository($items);

        $this->assertSame($items, $config->all());
    }

    #[Test]
    public function set_top_level_key(): void
    {
        $config = new ConfigRepository([]);

        $config->set('debug', true);

        $this->assertTrue($config->get('debug'));
    }

    #[Test]
    public function load_from_php_file(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-config-test-' . uniqid();
        mkdir($tmpDir, 0777, true);
        file_put_contents($tmpDir . '/app.php', '<?php return ["name" => "Lattice", "debug" => false];');

        $config = new ConfigRepository([]);
        $config->loadFromDirectory($tmpDir);

        $this->assertSame('Lattice', $config->get('app.name'));
        $this->assertFalse($config->get('app.debug'));

        unlink($tmpDir . '/app.php');
        rmdir($tmpDir);
    }
}

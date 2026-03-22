<?php

declare(strict_types=1);

namespace Lattice\Serializer\Tests\Unit;

use Lattice\Contracts\Serializer\SerializerInterface;
use Lattice\Serializer\JsonSerializer;
use Lattice\Serializer\PhpSerializer;
use Lattice\Serializer\SerializerManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SerializerManagerTest extends TestCase
{
    #[Test]
    public function test_register_and_get_serializer(): void
    {
        $manager = new SerializerManager();
        $json = new JsonSerializer();

        $manager->register('json', $json);

        $this->assertSame($json, $manager->get('json'));
    }

    #[Test]
    public function test_get_default_serializer(): void
    {
        $manager = new SerializerManager();
        $json = new JsonSerializer();
        $manager->register('json', $json);

        // Default is 'json'
        $this->assertSame($json, $manager->get());
    }

    #[Test]
    public function test_set_default_changes_default(): void
    {
        $manager = new SerializerManager();
        $json = new JsonSerializer();
        $php = new PhpSerializer();

        $manager->register('json', $json);
        $manager->register('php', $php);
        $manager->setDefault('php');

        $this->assertSame($php, $manager->get());
    }

    #[Test]
    public function test_get_null_returns_default(): void
    {
        $manager = new SerializerManager();
        $json = new JsonSerializer();
        $manager->register('json', $json);

        $this->assertSame($json, $manager->get(null));
    }

    #[Test]
    public function test_get_unregistered_serializer_throws(): void
    {
        $manager = new SerializerManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Serializer "xml" is not registered');

        $manager->get('xml');
    }

    #[Test]
    public function test_get_default_when_not_registered_throws(): void
    {
        $manager = new SerializerManager();

        $this->expectException(\InvalidArgumentException::class);

        $manager->get();
    }

    #[Test]
    public function test_register_multiple_serializers(): void
    {
        $manager = new SerializerManager();
        $json = new JsonSerializer();
        $php = new PhpSerializer();

        $manager->register('json', $json);
        $manager->register('php', $php);

        $this->assertSame($json, $manager->get('json'));
        $this->assertSame($php, $manager->get('php'));
    }

    #[Test]
    public function test_register_overwrites_existing(): void
    {
        $manager = new SerializerManager();
        $first = new JsonSerializer();
        $second = new JsonSerializer();

        $manager->register('json', $first);
        $manager->register('json', $second);

        $this->assertSame($second, $manager->get('json'));
    }
}

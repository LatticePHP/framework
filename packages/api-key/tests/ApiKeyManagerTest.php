<?php

declare(strict_types=1);

namespace Lattice\ApiKey\Tests;

use Lattice\ApiKey\ApiKeyManager;
use Lattice\ApiKey\ApiKeyPrincipal;
use Lattice\ApiKey\CreateApiKeyResult;
use Lattice\ApiKey\Store\InMemoryApiKeyStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiKeyManagerTest extends TestCase
{
    private ApiKeyManager $manager;
    private InMemoryApiKeyStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryApiKeyStore();
        $this->manager = new ApiKeyManager($this->store, 'lk');
    }

    #[Test]
    public function it_creates_an_api_key_with_prefix_format(): void
    {
        $result = $this->manager->create('Partner Key', ['read']);

        $this->assertInstanceOf(CreateApiKeyResult::class, $result);
        $this->assertNotEmpty($result->keyId);
        $this->assertStringStartsWith('lk_', $result->plainKey);
        $this->assertSame('Partner Key', $result->name);
        $this->assertSame(['read'], $result->scopes);
    }

    #[Test]
    public function it_validates_a_valid_key(): void
    {
        $result = $this->manager->create('My Key', ['read', 'write']);

        $principal = $this->manager->validate($result->plainKey);

        $this->assertInstanceOf(ApiKeyPrincipal::class, $principal);
        $this->assertSame($result->keyId, $principal->getId());
        $this->assertSame(['read', 'write'], $principal->getScopes());
    }

    #[Test]
    public function it_returns_null_for_invalid_key(): void
    {
        $this->assertNull($this->manager->validate('lk_nonexistent'));
    }

    #[Test]
    public function it_revokes_a_key(): void
    {
        $result = $this->manager->create('Key to Revoke', []);

        $this->manager->revoke($result->keyId);

        $this->assertNull($this->manager->validate($result->plainKey));
    }

    #[Test]
    public function it_lists_all_keys(): void
    {
        $this->manager->create('Key 1', ['read']);
        $this->manager->create('Key 2', ['write']);

        $keys = $this->manager->list();

        $this->assertCount(2, $keys);
    }

    #[Test]
    public function it_creates_key_with_metadata(): void
    {
        $result = $this->manager->create('Meta Key', ['read'], ['partner' => 'acme']);

        $principal = $this->manager->validate($result->plainKey);

        $this->assertNotNull($principal);
        $this->assertSame(['partner' => 'acme'], $principal->getMetadata());
    }

    #[Test]
    public function each_key_has_unique_id_and_plain_key(): void
    {
        $result1 = $this->manager->create('Key 1', []);
        $result2 = $this->manager->create('Key 2', []);

        $this->assertNotSame($result1->keyId, $result2->keyId);
        $this->assertNotSame($result1->plainKey, $result2->plainKey);
    }
}

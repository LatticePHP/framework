<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\ClientRepository;
use Lattice\OAuth\OAuthClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientRepositoryTest extends TestCase
{
    private ClientRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new ClientRepository();
    }

    #[Test]
    public function it_creates_and_finds_a_client(): void
    {
        $client = new OAuthClient(
            id: 'client-1',
            secretHash: password_hash('secret', PASSWORD_BCRYPT),
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            scopes: ['read'],
        );

        $this->repo->create($client);

        $found = $this->repo->find('client-1');
        $this->assertNotNull($found);
        $this->assertSame('client-1', $found->id);
        $this->assertSame('Test App', $found->name);
    }

    #[Test]
    public function it_returns_null_for_unknown_client(): void
    {
        $this->assertNull($this->repo->find('unknown'));
    }

    #[Test]
    public function it_throws_when_creating_duplicate(): void
    {
        $client = new OAuthClient(id: 'c1', secretHash: 'h', name: 'App');
        $this->repo->create($client);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client already exists');

        $this->repo->create($client);
    }

    #[Test]
    public function it_lists_all_clients(): void
    {
        $this->repo->create(new OAuthClient(id: 'c1', secretHash: 'h', name: 'App 1'));
        $this->repo->create(new OAuthClient(id: 'c2', secretHash: 'h', name: 'App 2'));

        $all = $this->repo->all();

        $this->assertCount(2, $all);
        $ids = array_map(fn(OAuthClient $c) => $c->id, $all);
        $this->assertContains('c1', $ids);
        $this->assertContains('c2', $ids);
    }

    #[Test]
    public function it_updates_a_client(): void
    {
        $this->repo->create(new OAuthClient(id: 'c1', secretHash: 'h', name: 'Old Name'));

        $updated = new OAuthClient(id: 'c1', secretHash: 'h', name: 'New Name');
        $this->repo->update('c1', $updated);

        $found = $this->repo->find('c1');
        $this->assertSame('New Name', $found->name);
    }

    #[Test]
    public function it_throws_when_updating_nonexistent_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client not found');

        $this->repo->update('unknown', new OAuthClient(id: 'unknown', secretHash: 'h', name: 'X'));
    }

    #[Test]
    public function it_deletes_a_client(): void
    {
        $this->repo->create(new OAuthClient(id: 'c1', secretHash: 'h', name: 'App'));
        $this->repo->delete('c1');

        $this->assertNull($this->repo->find('c1'));
    }

    #[Test]
    public function it_throws_when_deleting_nonexistent_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client not found');

        $this->repo->delete('unknown');
    }

    #[Test]
    public function it_validates_secret_for_confidential_client(): void
    {
        $hash = password_hash('my-secret', PASSWORD_BCRYPT);
        $this->repo->create(new OAuthClient(id: 'c1', secretHash: $hash, name: 'App', type: 'confidential'));

        $this->assertTrue($this->repo->validateSecret('c1', 'my-secret'));
        $this->assertFalse($this->repo->validateSecret('c1', 'wrong-secret'));
    }

    #[Test]
    public function it_returns_false_for_unknown_client_secret_validation(): void
    {
        $this->assertFalse($this->repo->validateSecret('unknown', 'secret'));
    }

    #[Test]
    public function it_allows_any_secret_for_public_client(): void
    {
        $this->repo->create(new OAuthClient(id: 'c1', secretHash: '', name: 'Public App', type: 'public'));

        $this->assertTrue($this->repo->validateSecret('c1', ''));
        $this->assertTrue($this->repo->validateSecret('c1', 'anything'));
    }

    #[Test]
    public function it_finds_or_fails(): void
    {
        $this->repo->create(new OAuthClient(id: 'c1', secretHash: 'h', name: 'App'));

        $found = $this->repo->findOrFail('c1');
        $this->assertSame('c1', $found->id);
    }

    #[Test]
    public function it_throws_on_find_or_fail_for_unknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client not found');

        $this->repo->findOrFail('unknown');
    }
}

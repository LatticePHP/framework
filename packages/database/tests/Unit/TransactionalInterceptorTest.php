<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Database\ConnectionConfig;
use Lattice\Database\ConnectionManager;
use Lattice\Database\TransactionalInterceptor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionalInterceptorTest extends TestCase
{
    private ConnectionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ConnectionManager();
        $this->manager->addConnection('default', ConnectionConfig::sqlite(':memory:'));

        $conn = $this->manager->connection('default');
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }

    private function createContext(): ExecutionContextInterface
    {
        return new class implements ExecutionContextInterface {
            public function getType(): ExecutionType
            {
                return ExecutionType::HTTP;
            }
            public function getModule(): string
            {
                return 'test';
            }
            public function getHandler(): string
            {
                return 'handler';
            }
            public function getClass(): string
            {
                return 'TestClass';
            }
            public function getMethod(): string
            {
                return 'testMethod';
            }
            public function getCorrelationId(): string
            {
                return 'test-123';
            }
            public function getPrincipal(): ?PrincipalInterface
            {
                return null;
            }
        };
    }

    #[Test]
    public function it_commits_on_success(): void
    {
        $interceptor = new TransactionalInterceptor($this->manager);
        $context = $this->createContext();
        $conn = $this->manager->connection('default');

        $result = $interceptor->intercept($context, function () use ($conn) {
            $conn->execute("INSERT INTO users (name) VALUES (?)", ['Alice']);
            return 'success';
        });

        $this->assertSame('success', $result);
        $rows = $conn->query('SELECT * FROM users');
        $this->assertCount(1, $rows);
    }

    #[Test]
    public function it_rolls_back_on_exception(): void
    {
        $interceptor = new TransactionalInterceptor($this->manager);
        $context = $this->createContext();
        $conn = $this->manager->connection('default');

        try {
            $interceptor->intercept($context, function () use ($conn) {
                $conn->execute("INSERT INTO users (name) VALUES (?)", ['Alice']);
                throw new \RuntimeException('Something went wrong');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $rows = $conn->query('SELECT * FROM users');
        $this->assertCount(0, $rows);
    }

    #[Test]
    public function it_rethrows_the_exception(): void
    {
        $interceptor = new TransactionalInterceptor($this->manager);
        $context = $this->createContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fail!');

        $interceptor->intercept($context, function () {
            throw new \RuntimeException('fail!');
        });
    }
}

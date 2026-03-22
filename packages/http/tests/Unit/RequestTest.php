<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCreateRequest(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users',
            headers: ['Content-Type' => 'application/json'],
            query: ['page' => '1'],
            body: null,
        );

        $this->assertSame('GET', $request->method);
        $this->assertSame('/users', $request->uri);
    }

    public function testGetBody(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $request = new Request(
            method: 'POST',
            uri: '/users',
            headers: [],
            query: [],
            body: $data,
        );

        $this->assertSame($data, $request->getBody());
    }

    public function testGetQuery(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users',
            headers: [],
            query: ['page' => '2', 'limit' => '10'],
            body: null,
        );

        $this->assertSame('2', $request->getQuery('page'));
        $this->assertSame('10', $request->getQuery('limit'));
        $this->assertNull($request->getQuery('nonexistent'));
    }

    public function testGetHeader(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users',
            headers: ['Authorization' => 'Bearer token123', 'X-Request-Id' => 'abc'],
            query: [],
            body: null,
        );

        $this->assertSame('Bearer token123', $request->getHeader('Authorization'));
        $this->assertSame('abc', $request->getHeader('X-Request-Id'));
        $this->assertNull($request->getHeader('X-Missing'));
    }

    public function testGetParam(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users/42',
            headers: [],
            query: [],
            body: null,
            pathParams: ['id' => '42'],
        );

        $this->assertSame('42', $request->getParam('id'));
        $this->assertNull($request->getParam('nonexistent'));
    }

    public function testWithPathParams(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users/42',
            headers: [],
            query: [],
            body: null,
        );

        $newRequest = $request->withPathParams(['id' => '42']);

        $this->assertNull($request->getParam('id'));
        $this->assertSame('42', $newRequest->getParam('id'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/test',
            headers: ['Content-Type' => 'application/json'],
            query: [],
            body: null,
        );

        $this->assertSame('application/json', $request->getHeader('content-type'));
        $this->assertSame('application/json', $request->getHeader('CONTENT-TYPE'));
    }
}

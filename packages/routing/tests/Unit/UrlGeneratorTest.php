<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use Lattice\Routing\UrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    private UrlGenerator $generator;
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();

        // Register named routes
        $this->router->addRoute(new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'UserController',
            methodName: 'index',
            parameterBindings: [],
            name: 'users.index',
        ));
        $this->router->addRoute(new RouteDefinition(
            httpMethod: 'GET',
            path: '/users/{id}',
            controllerClass: 'UserController',
            methodName: 'show',
            parameterBindings: [],
            name: 'users.show',
        ));
        $this->router->addRoute(new RouteDefinition(
            httpMethod: 'GET',
            path: '/posts/{postId}/comments/{commentId}',
            controllerClass: 'CommentController',
            methodName: 'show',
            parameterBindings: [],
            name: 'comments.show',
        ));

        $this->generator = new UrlGenerator($this->router);
        $this->generator->setBaseUrl('https://api.example.com');
    }

    #[Test]
    public function test_route_generates_url_for_named_route(): void
    {
        $url = $this->generator->route('users.index');

        $this->assertEquals('/users', $url);
    }

    #[Test]
    public function test_route_with_parameters(): void
    {
        $url = $this->generator->route('users.show', ['id' => '42']);

        $this->assertEquals('/users/42', $url);
    }

    #[Test]
    public function test_route_with_multiple_parameters(): void
    {
        $url = $this->generator->route('comments.show', [
            'postId' => '10',
            'commentId' => '5',
        ]);

        $this->assertEquals('/posts/10/comments/5', $url);
    }

    #[Test]
    public function test_route_throws_for_unknown_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->route('nonexistent');
    }

    #[Test]
    public function test_to_generates_path_with_query_params(): void
    {
        $url = $this->generator->to('/search', ['q' => 'php', 'page' => '2']);

        $this->assertEquals('/search?q=php&page=2', $url);
    }

    #[Test]
    public function test_to_without_query_params(): void
    {
        $url = $this->generator->to('/about');

        $this->assertEquals('/about', $url);
    }

    #[Test]
    public function test_full_generates_absolute_url(): void
    {
        $url = $this->generator->full('/users');

        $this->assertEquals('https://api.example.com/users', $url);
    }

    #[Test]
    public function test_full_strips_trailing_slash_from_base(): void
    {
        $this->generator->setBaseUrl('https://api.example.com/');
        $url = $this->generator->full('/users');

        $this->assertEquals('https://api.example.com/users', $url);
    }

    #[Test]
    public function test_set_base_url(): void
    {
        $this->generator->setBaseUrl('https://other.com');
        $url = $this->generator->full('/test');

        $this->assertEquals('https://other.com/test', $url);
    }
}

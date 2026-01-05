<?php

declare(strict_types=1);

namespace Forjix\Http\Tests;

use Forjix\Http\Router;
use Forjix\Http\Route;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testGetRoute(): void
    {
        $this->router->get('/users', fn() => 'users');

        $routes = $this->router->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('GET', $routes[0]->getMethod());
        $this->assertEquals('/users', $routes[0]->getPath());
    }

    public function testPostRoute(): void
    {
        $this->router->post('/users', fn() => 'create');

        $routes = $this->router->getRoutes();
        $this->assertEquals('POST', $routes[0]->getMethod());
    }

    public function testPutRoute(): void
    {
        $this->router->put('/users/{id}', fn($id) => "update $id");

        $routes = $this->router->getRoutes();
        $this->assertEquals('PUT', $routes[0]->getMethod());
    }

    public function testPatchRoute(): void
    {
        $this->router->patch('/users/{id}', fn($id) => "patch $id");

        $routes = $this->router->getRoutes();
        $this->assertEquals('PATCH', $routes[0]->getMethod());
    }

    public function testDeleteRoute(): void
    {
        $this->router->delete('/users/{id}', fn($id) => "delete $id");

        $routes = $this->router->getRoutes();
        $this->assertEquals('DELETE', $routes[0]->getMethod());
    }

    public function testRouteMatching(): void
    {
        $this->router->get('/users', fn() => 'list');
        $this->router->get('/users/{id}', fn($id) => "show $id");

        $route = $this->router->match('GET', '/users');
        $this->assertNotNull($route);
        $this->assertEquals('/users', $route->getPath());

        $route = $this->router->match('GET', '/users/123');
        $this->assertNotNull($route);
        $this->assertEquals('/users/{id}', $route->getPath());
    }

    public function testRouteNotFound(): void
    {
        $this->router->get('/users', fn() => 'list');

        $route = $this->router->match('GET', '/posts');
        $this->assertNull($route);
    }

    public function testRouteParameters(): void
    {
        $this->router->get('/users/{id}/posts/{postId}', fn($id, $postId) => "user $id post $postId");

        $route = $this->router->match('GET', '/users/1/posts/5');
        $params = $route->getParameters();

        $this->assertEquals('1', $params['id']);
        $this->assertEquals('5', $params['postId']);
    }

    public function testRouteGroup(): void
    {
        $this->router->group(['prefix' => '/api'], function (Router $router) {
            $router->get('/users', fn() => 'users');
        });

        $route = $this->router->match('GET', '/api/users');
        $this->assertNotNull($route);
    }

    public function testRouteMiddleware(): void
    {
        $this->router->get('/admin', fn() => 'admin')->middleware('auth');

        $routes = $this->router->getRoutes();
        $this->assertContains('auth', $routes[0]->getMiddleware());
    }
}

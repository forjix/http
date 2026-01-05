<?php

declare(strict_types=1);

namespace Forjix\Http;

use Closure;
use Forjix\Core\Container\Container;
use Forjix\Http\Attributes\Controller;
use Forjix\Http\Attributes\Middleware;
use Forjix\Http\Attributes\Route as RouteAttribute;
use Forjix\Http\Exceptions\HttpException;
use Forjix\Http\Exceptions\NotFoundException;
use Forjix\Support\Pipeline;
use ReflectionClass;
use ReflectionMethod;

class Router
{
    protected array $routes = [];
    protected array $namedRoutes = [];
    protected array $patterns = [];
    protected array $groupStack = [];
    protected ?Container $container = null;
    protected array $middleware = [];
    protected array $middlewareGroups = [];
    protected array $middlewareAliases = [];

    public function __construct(?Container $container = null)
    {
        $this->container = $container;

        $this->patterns = [
            'id' => '[0-9]+',
            'slug' => '[a-z0-9-]+',
            'uuid' => '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}',
            'any' => '.*',
        ];
    }

    public function get(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    public function post(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function any(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    public function match(array $methods, string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    public function group(array $attributes, Closure $routes): void
    {
        $this->groupStack[] = $attributes;

        $routes($this);

        array_pop($this->groupStack);
    }

    public function prefix(string $prefix): static
    {
        $this->groupStack[] = ['prefix' => $prefix];

        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $middleware = (array) $middleware;

        if (!empty($this->groupStack)) {
            $this->groupStack[count($this->groupStack) - 1]['middleware'] = array_merge(
                $this->groupStack[count($this->groupStack) - 1]['middleware'] ?? [],
                $middleware
            );
        }

        return $this;
    }

    protected function addRoute(array $methods, string $uri, array|string|Closure $action): Route
    {
        $uri = $this->prefix($uri);
        $middleware = $this->getGroupMiddleware();

        $route = new Route($methods, $uri, $action);
        $route->middleware($middleware);

        foreach ($methods as $method) {
            $this->routes[$method][] = $route;
        }

        return $route;
    }

    protected function prefix(string $uri): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }

        return $prefix . '/' . trim($uri, '/');
    }

    protected function getGroupMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        return $middleware;
    }

    public function registerController(string $controller): void
    {
        $reflection = new ReflectionClass($controller);
        $controllerAttribute = $this->getControllerAttribute($reflection);

        $prefix = $controllerAttribute?->prefix ?? '';
        $controllerMiddleware = $controllerAttribute?->middleware ?? [];

        // Get class-level middleware
        $classMiddleware = $this->getClassMiddleware($reflection);
        $controllerMiddleware = array_merge($controllerMiddleware, $classMiddleware);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $this->registerControllerMethod($controller, $method, $prefix, $controllerMiddleware);
        }
    }

    protected function registerControllerMethod(
        string $controller,
        ReflectionMethod $method,
        string $prefix,
        array $controllerMiddleware
    ): void {
        $routeAttributes = $method->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
        $methodMiddleware = $this->getMethodMiddleware($method);

        foreach ($routeAttributes as $attribute) {
            $route = $attribute->newInstance();
            $uri = '/' . trim($prefix, '/') . '/' . trim($route->path, '/');
            $uri = preg_replace('#/+#', '/', $uri);

            $middleware = array_merge($controllerMiddleware, $route->middleware, $methodMiddleware);

            $newRoute = new Route(
                $route->methods,
                $uri,
                [$controller, $method->getName()]
            );

            $newRoute->middleware($middleware);

            if ($route->name) {
                $newRoute->name($route->name);
                $this->namedRoutes[$route->name] = $newRoute;
            }

            foreach ($route->where as $param => $pattern) {
                $newRoute->where($param, $pattern);
            }

            foreach ($route->methods as $httpMethod) {
                $this->routes[$httpMethod][] = $newRoute;
            }
        }
    }

    protected function getControllerAttribute(ReflectionClass $reflection): ?Controller
    {
        $attributes = $reflection->getAttributes(Controller::class);

        return empty($attributes) ? null : $attributes[0]->newInstance();
    }

    protected function getClassMiddleware(ReflectionClass $reflection): array
    {
        $middleware = [];
        $attributes = $reflection->getAttributes(Middleware::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $middleware = array_merge($middleware, $instance->middleware);
        }

        return $middleware;
    }

    protected function getMethodMiddleware(ReflectionMethod $method): array
    {
        $middleware = [];
        $attributes = $method->getAttributes(Middleware::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $middleware = array_merge($middleware, $instance->middleware);
        }

        return $middleware;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $route = $this->findRoute($method, $path);

        if ($route === null) {
            throw new NotFoundException('Route not found');
        }

        $request->setAttribute('route', $route);

        foreach ($route->getParameters() as $key => $value) {
            $request->setAttribute($key, $value);
        }

        return $this->runRoute($request, $route);
    }

    protected function findRoute(string $method, string $path): ?Route
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if ($route->matches($path, $this->patterns)) {
                return $route;
            }
        }

        return null;
    }

    protected function runRoute(Request $request, Route $route): Response
    {
        $middleware = $this->gatherRouteMiddleware($route);

        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then(fn($request) => $this->runRouteAction($request, $route));
    }

    protected function gatherRouteMiddleware(Route $route): array
    {
        $middleware = [];

        foreach ($route->getMiddleware() as $name) {
            $resolved = $this->resolveMiddleware($name);
            $middleware = array_merge($middleware, (array) $resolved);
        }

        return array_unique($middleware);
    }

    protected function resolveMiddleware(string $name): string|array
    {
        if (isset($this->middlewareAliases[$name])) {
            return $this->middlewareAliases[$name];
        }

        if (isset($this->middlewareGroups[$name])) {
            return $this->middlewareGroups[$name];
        }

        return $name;
    }

    protected function runRouteAction(Request $request, Route $route): Response
    {
        $action = $route->getAction();

        if ($action instanceof Closure) {
            $response = $this->container
                ? $this->container->call($action, ['request' => $request])
                : $action($request);
        } else {
            [$controller, $method] = $action;

            $instance = $this->container
                ? $this->container->make($controller)
                : new $controller();

            $response = $this->container
                ? $this->container->call([$instance, $method], ['request' => $request])
                : $instance->{$method}($request);
        }

        return $this->prepareResponse($response);
    }

    protected function prepareResponse(mixed $response): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if (is_array($response) || $response instanceof \JsonSerializable) {
            return new JsonResponse($response);
        }

        return new Response((string) $response);
    }

    public function aliasMiddleware(string $name, string $class): static
    {
        $this->middlewareAliases[$name] = $class;

        return $this;
    }

    public function middlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    public function pattern(string $key, string $pattern): static
    {
        $this->patterns[$key] = $pattern;

        return $this;
    }

    public function patterns(array $patterns): static
    {
        foreach ($patterns as $key => $pattern) {
            $this->pattern($key, $pattern);
        }

        return $this;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        return $this->namedRoutes[$name]->url($parameters);
    }
}

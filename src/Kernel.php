<?php

declare(strict_types=1);

namespace Forjix\Http;

use Forjix\Core\Application;
use Forjix\Http\Exceptions\HttpException;
use Forjix\Support\Pipeline;
use Throwable;

class Kernel
{
    protected Application $app;
    protected Router $router;

    protected array $middleware = [];

    protected array $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];

    protected array $middlewareAliases = [];

    protected array $middlewarePriority = [];

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;

        $this->syncMiddlewareToRouter();
    }

    protected function syncMiddlewareToRouter(): void
    {
        foreach ($this->middlewareGroups as $group => $middleware) {
            $this->router->middlewareGroup($group, $middleware);
        }

        foreach ($this->middlewareAliases as $alias => $middleware) {
            $this->router->aliasMiddleware($alias, $middleware);
        }
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            $response = $this->handleException($request, $e);
        }

        return $response;
    }

    protected function sendRequestThroughRouter(Request $request): Response
    {
        $this->app->instance(Request::class, $request);

        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->middleware)
            ->then(fn($request) => $this->router->dispatch($request));
    }

    protected function handleException(Request $request, Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            return $this->renderHttpException($e, $request);
        }

        if ($this->app->environment('local')) {
            return $this->renderExceptionWithDetails($e);
        }

        return new Response('Server Error', 500);
    }

    protected function renderHttpException(HttpException $e, Request $request): Response
    {
        $statusCode = $e->getStatusCode();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $e->getMessage() ?: $this->getStatusText($statusCode),
            ], $statusCode, $e->getHeaders());
        }

        return new Response(
            $e->getMessage() ?: $this->getStatusText($statusCode),
            $statusCode,
            $e->getHeaders()
        );
    }

    protected function renderExceptionWithDetails(Throwable $e): Response
    {
        $content = sprintf(
            "<h1>%s</h1><p>%s</p><pre>%s</pre>",
            get_class($e),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return new Response($content, 500);
    }

    protected function getStatusText(int $code): string
    {
        return match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Unknown Error',
        };
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->app->terminate();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getApplication(): Application
    {
        return $this->app;
    }

    public function prependMiddleware(string $middleware): static
    {
        array_unshift($this->middleware, $middleware);

        return $this;
    }

    public function pushMiddleware(string $middleware): static
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function prependMiddlewareToGroup(string $group, string $middleware): static
    {
        if (!isset($this->middlewareGroups[$group])) {
            $this->middlewareGroups[$group] = [];
        }

        array_unshift($this->middlewareGroups[$group], $middleware);
        $this->syncMiddlewareToRouter();

        return $this;
    }

    public function appendMiddlewareToGroup(string $group, string $middleware): static
    {
        if (!isset($this->middlewareGroups[$group])) {
            $this->middlewareGroups[$group] = [];
        }

        $this->middlewareGroups[$group][] = $middleware;
        $this->syncMiddlewareToRouter();

        return $this;
    }
}

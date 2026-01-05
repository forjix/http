<?php

declare(strict_types=1);

namespace Forjix\Http;

use Closure;

class Route
{
    protected array $methods;
    protected string $uri;
    protected array|string|Closure $action;
    protected ?string $name = null;
    protected array $middleware = [];
    protected array $where = [];
    protected array $parameters = [];
    protected ?string $compiledPattern = null;

    public function __construct(array $methods, string $uri, array|string|Closure $action)
    {
        $this->methods = $methods;
        $this->uri = '/' . trim($uri, '/');
        $this->action = $action;
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    public function where(string|array $name, ?string $expression = null): static
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                $this->where[$key] = $value;
            }
        } else {
            $this->where[$name] = $expression;
        }

        $this->compiledPattern = null;

        return $this;
    }

    public function whereNumber(string $name): static
    {
        return $this->where($name, '[0-9]+');
    }

    public function whereAlpha(string $name): static
    {
        return $this->where($name, '[a-zA-Z]+');
    }

    public function whereAlphaNumeric(string $name): static
    {
        return $this->where($name, '[a-zA-Z0-9]+');
    }

    public function whereUuid(string $name): static
    {
        return $this->where($name, '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}');
    }

    public function matches(string $path, array $patterns = []): bool
    {
        $pattern = $this->compile($patterns);

        if (preg_match($pattern, $path, $matches)) {
            $this->parameters = array_filter(
                $matches,
                fn($key) => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            return true;
        }

        return false;
    }

    protected function compile(array $patterns = []): string
    {
        if ($this->compiledPattern !== null) {
            return $this->compiledPattern;
        }

        $uri = $this->uri;

        // Handle optional parameters: {param?}
        $uri = preg_replace_callback('/\{(\w+)\?\}/', function ($match) use ($patterns) {
            $name = $match[1];
            $pattern = $this->where[$name] ?? $patterns[$name] ?? '[^/]+';

            return "(?:(?P<{$name}>{$pattern}))?";
        }, $uri);

        // Handle required parameters: {param}
        $uri = preg_replace_callback('/\{(\w+)\}/', function ($match) use ($patterns) {
            $name = $match[1];
            $pattern = $this->where[$name] ?? $patterns[$name] ?? '[^/]+';

            return "(?P<{$name}>{$pattern})";
        }, $uri);

        return $this->compiledPattern = '#^' . $uri . '/?$#';
    }

    public function url(array $parameters = []): string
    {
        $uri = $this->uri;

        // Replace parameters
        $uri = preg_replace_callback('/\{(\w+)\??}/', function ($match) use ($parameters) {
            $name = $match[1];

            if (isset($parameters[$name])) {
                return $parameters[$name];
            }

            // Optional parameter without value
            if (str_ends_with($match[0], '?}')) {
                return '';
            }

            throw new \InvalidArgumentException("Missing parameter [{$name}] for route.");
        }, $uri);

        // Clean up double slashes
        return preg_replace('#/+#', '/', $uri);
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): array|string|Closure
    {
        return $this->action;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }
}

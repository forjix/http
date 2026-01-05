<?php

declare(strict_types=1);

namespace Forjix\Http;

use Forjix\Support\Arr;

class Request
{
    protected array $query = [];
    protected array $request = [];
    protected array $attributes = [];
    protected array $cookies = [];
    protected array $files = [];
    protected array $server = [];
    protected ?string $content = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->content = $content;
    }

    public static function capture(): static
    {
        return static::createFromGlobals();
    }

    public static function createFromGlobals(): static
    {
        return new static(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input') ?: null
        );
    }

    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): static {
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Forjix',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ], $server);

        $server['REQUEST_METHOD'] = strtoupper($method);
        $server['REQUEST_URI'] = $uri;

        $query = [];
        $request = [];

        if (in_array(strtoupper($method), ['GET', 'HEAD', 'DELETE'])) {
            $query = $parameters;
        } else {
            $request = $parameters;
        }

        return new static($query, $request, [], $cookies, $files, $server, $content);
    }

    public function method(): string
    {
        return strtoupper($this->server('REQUEST_METHOD', 'GET'));
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function path(): string
    {
        $uri = $this->server('REQUEST_URI', '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return $path === '' ? '/' : $path;
    }

    public function url(): string
    {
        return rtrim(preg_replace('/\?.*/', '', $this->fullUrl()), '/');
    }

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->host();
        $uri = $this->server('REQUEST_URI', '/');

        return "{$scheme}://{$host}{$uri}";
    }

    public function host(): string
    {
        return $this->server('HTTP_HOST', $this->server('SERVER_NAME', 'localhost'));
    }

    public function isSecure(): bool
    {
        $https = $this->server('HTTPS');

        return $https !== null && $https !== 'off';
    }

    public function ip(): ?string
    {
        return $this->server('REMOTE_ADDR');
    }

    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $this->server($key, $default);
    }

    public function headers(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return Arr::get($this->query, $key, $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return Arr::get($this->request, $key, $default);
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_merge($this->query, $this->request, $this->json());

        if ($key === null) {
            return $input;
        }

        return Arr::get($input, $key, $default);
    }

    public function all(): array
    {
        return $this->input();
    }

    public function only(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::only($this->all(), $keys);
    }

    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::except($this->all(), $keys);
    }

    public function has(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->all();

        foreach ($keys as $k) {
            if (!Arr::has($input, $k)) {
                return false;
            }
        }

        return true;
    }

    public function filled(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $k) {
            if (blank($this->input($k))) {
                return false;
            }
        }

        return true;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        static $json = null;

        if ($json === null) {
            $content = $this->getContent();
            $json = json_decode($content ?: '{}', true) ?: [];
        }

        if ($key === null) {
            return $json;
        }

        return Arr::get($json, $key, $default);
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }

        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), 'json');
    }

    public function expectsJson(): bool
    {
        return $this->isXmlHttpRequest() || str_contains($this->header('Accept', ''), 'json');
    }

    public function isXmlHttpRequest(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function isAjax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    public function accepts(string|array $contentTypes): bool
    {
        $accept = $this->header('Accept', '*/*');

        foreach ((array) $contentTypes as $type) {
            if (str_contains($accept, $type) || $accept === '*/*') {
                return true;
            }
        }

        return false;
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept', ''), '/json') ||
               str_contains($this->header('Accept', ''), '+json');
    }
}

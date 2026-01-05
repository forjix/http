# Forjix HTTP

HTTP layer for the Forjix framework including routing, requests, responses, and middleware.

## Installation

```bash
composer require forjix/http
```

## Routing

### Basic Routes

```php
use Forjix\Http\Router;

$router = new Router();

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
```

### Attribute Routing

```php
use Forjix\Http\Attributes\Controller;
use Forjix\Http\Attributes\Get;
use Forjix\Http\Attributes\Post;

#[Controller('/users')]
class UserController
{
    #[Get('/')]
    public function index(): Response { }

    #[Get('/{id}')]
    public function show(int $id): Response { }

    #[Post('/')]
    public function store(Request $request): Response { }
}
```

## Request

```php
public function store(Request $request): Response
{
    $name = $request->input('name');
    $email = $request->input('email', 'default@example.com');
    $all = $request->all();

    // File uploads
    $file = $request->file('avatar');
}
```

## Responses

```php
use Forjix\Http\Response;
use Forjix\Http\JsonResponse;
use Forjix\Http\RedirectResponse;

// HTML response
return new Response('<h1>Hello</h1>');

// JSON response
return new JsonResponse(['status' => 'ok']);

// Redirect
return new RedirectResponse('/dashboard');
```

## Middleware

```php
use Forjix\Http\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->user()) {
            return new RedirectResponse('/login');
        }

        return $next($request);
    }
}
```

## License

GPL-3.0

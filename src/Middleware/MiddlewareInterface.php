<?php

declare(strict_types=1);

namespace Forjix\Http\Middleware;

use Closure;
use Forjix\Http\Request;
use Forjix\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response;
}

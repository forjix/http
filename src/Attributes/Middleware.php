<?php

declare(strict_types=1);

namespace Forjix\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public array $middleware;

    public function __construct(string|array ...$middleware)
    {
        $this->middleware = array_merge(...array_map(fn($m) => (array) $m, $middleware));
    }
}

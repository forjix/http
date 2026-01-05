<?php

declare(strict_types=1);

namespace Forjix\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Get extends Route
{
    public function __construct(
        string $path = '',
        ?string $name = null,
        array $middleware = [],
        array $where = []
    ) {
        parent::__construct($path, 'GET', $name, $middleware, $where);
    }
}

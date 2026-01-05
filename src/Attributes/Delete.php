<?php

declare(strict_types=1);

namespace Forjix\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Delete extends Route
{
    public function __construct(
        string $path = '',
        ?string $name = null,
        array $middleware = [],
        array $where = []
    ) {
        parent::__construct($path, 'DELETE', $name, $middleware, $where);
    }
}

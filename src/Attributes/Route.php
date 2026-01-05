<?php

declare(strict_types=1);

namespace Forjix\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public array|string $methods = ['GET'],
        public ?string $name = null,
        public array $middleware = [],
        public array $where = []
    ) {
        $this->methods = (array) $this->methods;
    }
}

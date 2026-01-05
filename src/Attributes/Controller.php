<?php

declare(strict_types=1);

namespace Forjix\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(
        public string $prefix = '',
        public ?string $name = null,
        public array $middleware = []
    ) {
    }
}

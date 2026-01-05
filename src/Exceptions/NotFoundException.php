<?php

declare(strict_types=1);

namespace Forjix\Http\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found', array $headers = [])
    {
        parent::__construct(404, $message, null, $headers);
    }
}

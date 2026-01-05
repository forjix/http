<?php

declare(strict_types=1);

namespace Forjix\Http\Exceptions;

use Exception;
use Throwable;

class HttpException extends Exception
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(
        int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}

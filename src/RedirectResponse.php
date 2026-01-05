<?php

declare(strict_types=1);

namespace Forjix\Http;

class RedirectResponse extends Response
{
    protected string $targetUrl;

    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);

        $this->setTargetUrl($url);
    }

    public static function to(string $url, int $status = 302, array $headers = []): static
    {
        return new static($url, $status, $headers);
    }

    public function setTargetUrl(string $url): static
    {
        if ($url === '') {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->targetUrl = $url;
        $this->setContent(sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />
        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES)));

        $this->header('Location', $url);

        return $this;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function with(string $key, mixed $value): static
    {
        session_start();
        $_SESSION['_flash'][$key] = $value;

        return $this;
    }

    public function withInput(array $input = []): static
    {
        session_start();
        $_SESSION['_old_input'] = $input;

        return $this;
    }

    public function withErrors(array $errors): static
    {
        session_start();
        $_SESSION['_errors'] = $errors;

        return $this;
    }
}

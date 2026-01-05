<?php

declare(strict_types=1);

namespace Forjix\Http;

class JsonResponse extends Response
{
    protected int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(mixed $data = null, int $status = 200, array $headers = [], int $options = 0)
    {
        $this->encodingOptions = $options ?: $this->encodingOptions;

        parent::__construct('', $status, $headers);

        $this->header('Content-Type', 'application/json');
        $this->setData($data);
    }

    public static function make(mixed $data = null, int $status = 200, array $headers = []): static
    {
        return new static($data, $status, $headers);
    }

    public function setData(mixed $data = null): static
    {
        $json = json_encode($data, $this->encodingOptions);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Failed to encode data as JSON: ' . json_last_error_msg());
        }

        return $this->setContent($json);
    }

    public function getData(bool $assoc = true): mixed
    {
        return json_decode($this->getContent(), $assoc);
    }

    public function setEncodingOptions(int $options): static
    {
        $this->encodingOptions = $options;

        return $this->setData($this->getData());
    }

    public function getEncodingOptions(): int
    {
        return $this->encodingOptions;
    }
}

<?php

declare(strict_types=1);

namespace Forjix\Http\Tests;

use Forjix\Http\Response;
use Forjix\Http\JsonResponse;
use Forjix\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testResponseContent(): void
    {
        $response = new Response('Hello World');

        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testResponseStatusCode(): void
    {
        $response = new Response('', 201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testResponseHeaders(): void
    {
        $response = new Response('', 200, ['X-Custom' => 'value']);

        $this->assertEquals('value', $response->getHeader('X-Custom'));
    }

    public function testSetContent(): void
    {
        $response = new Response();
        $response->setContent('New content');

        $this->assertEquals('New content', $response->getContent());
    }

    public function testSetStatusCode(): void
    {
        $response = new Response();
        $response->setStatusCode(404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetHeader(): void
    {
        $response = new Response();
        $response->setHeader('Content-Type', 'application/json');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testJsonResponse(): void
    {
        $response = new JsonResponse(['message' => 'Hello']);

        $this->assertEquals('{"message":"Hello"}', $response->getContent());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testJsonResponseWithStatusCode(): void
    {
        $response = new JsonResponse(['error' => 'Not found'], 404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRedirectResponse(): void
    {
        $response = new RedirectResponse('/dashboard');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeader('Location'));
    }

    public function testRedirectResponseWithStatusCode(): void
    {
        $response = new RedirectResponse('/dashboard', 301);

        $this->assertEquals(301, $response->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use function NixPHP\app;

final class JsonRequest
{
    /** @param array<string, mixed> $payload */
    public static function bind(array $payload, string $path = '/api/test'): ServerRequest
    {
        $request = (new ServerRequest('POST', $path))
            ->withHeader('Content-Type', 'application/json')
            ->withBody(Stream::create(json_encode($payload, JSON_THROW_ON_ERROR)))
            ->withParsedBody($payload);
        app()->container()->set(RequestInterface::class, $request);
        app()->container()->set(ServerRequestInterface::class, $request);

        return $request;
    }
}

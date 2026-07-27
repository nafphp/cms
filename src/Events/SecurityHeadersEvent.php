<?php

declare(strict_types=1);

namespace src\Events;

use Psr\Http\Message\ResponseInterface;
use function NixPHP\request;

final class SecurityHeadersEvent
{
    private const string CONTENT_SECURITY_POLICY = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; media-src 'self' https:; font-src 'self' data:; frame-src https:; connect-src 'self' http://localhost:* http://127.0.0.1:* http://[::1]:*";

    public function handle(ResponseInterface $response): ResponseInterface
    {
        $response = $response
            ->withHeader('Content-Security-Policy', self::CONTENT_SECURITY_POLICY)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        try {
            if (strtolower(request()->getUri()->getScheme()) === 'https') {
                $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        } catch (\Throwable) {
        }

        return $response;
    }
}

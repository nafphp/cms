<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

use Naf\CMS\Security\CspNonce;
use Psr\Http\Message\ResponseInterface;
use function Naf\config;
use function Naf\request;

final class SecurityHeadersEvent
{
    public function handle(ResponseInterface $response): ResponseInterface
    {
        $response = $response
            ->withHeader('Content-Security-Policy', $this->policy())
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

    private function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            sprintf("script-src 'self' 'nonce-%s'", CspNonce::value()),
            // Theme previews still rely on inline style attributes. Script
            // nonces remove the executable-inline risk independently.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "media-src 'self' https:",
            "font-src 'self' data:",
            "frame-src https:",
            $this->connectSrc(),
        ]);
    }

    private function connectSrc(): string
    {
        $enabled = filter_var(config('ai:enabled', false), FILTER_VALIDATE_BOOL);
        $local = (string) config('ai:type', 'local') === 'local';

        return $enabled && $local
            ? "connect-src 'self' http://localhost:* http://127.0.0.1:* http://[::1]:*"
            : "connect-src 'self'";
    }
}

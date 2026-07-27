<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use src\Events\SecurityHeadersEvent;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersEventTest extends TestCase
{
    public function testCentralHeaderBaselineIsApplied(): void
    {
        $response = (new SecurityHeadersEvent())->handle(new Response(200, [], '<!doctype html>'));

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString('frame-ancestors \'none\'', $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString("object-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString('camera=()', $response->getHeaderLine('Permissions-Policy'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use NixPHP\CMS\Events\SecurityHeadersEvent;
use NixPHP\CMS\Security\CspNonce;
use NixPHP\Core\Config;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use function NixPHP\app;

final class SecurityHeadersEventTest extends TestCase
{
    private Config $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConfig = app()->container()->get(Config::class);
        CspNonce::reset();
    }

    protected function tearDown(): void
    {
        app()->container()->set(Config::class, $this->originalConfig);
        CspNonce::reset();
        parent::tearDown();
    }

    public function testCentralHeaderBaselineIsApplied(): void
    {
        $response = (new SecurityHeadersEvent())->handle(new Response(200, [], '<!doctype html>'));

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString('frame-ancestors \'none\'', $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString("object-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringContainsString(
            "script-src 'self' 'nonce-" . CspNonce::value() . "'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
        self::assertStringContainsString('camera=()', $response->getHeaderLine('Permissions-Policy'));
        self::assertStringNotContainsString('http://localhost:', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testLocalAiEnablesLoopbackConnectTargets(): void
    {
        $config = $this->originalConfig->all();
        $config['ai']['enabled'] = true;
        $config['ai']['type'] = 'local';
        app()->container()->set(Config::class, new Config($config));

        $response = (new SecurityHeadersEvent())->handle(new Response());
        self::assertStringContainsString('http://localhost:*', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testNonceChangesAfterRequestReset(): void
    {
        $first = CspNonce::value();
        CspNonce::reset();
        self::assertNotSame($first, CspNonce::value());
    }
}

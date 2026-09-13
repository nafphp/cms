<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\Session\Core\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionCookieBaselineTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHttpsSessionCookieUsesSecureHttpOnlyAndSameSiteDefaults(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'cms.example.test';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

        $session = new Session();
        $session->start();
        $params = session_get_cookie_params();

        self::assertTrue($params['secure']);
        self::assertTrue($params['httponly']);
        self::assertSame('Lax', $params['samesite']);
        self::assertSame('/', $params['path']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testForwardedHttpsIsIgnoredUnlessProxyIsExplicitlyTrusted(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_HOST'] = 'cms.example.test';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

        $session = new Session();
        $session->configureProxyTrust(true, ['203.0.113.11']);
        $session->start();

        self::assertFalse(session_get_cookie_params()['secure']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\CMS\Events\AuthRedirectEvent;
use Naf\CMS\Exceptions\AuthException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use function Naf\app;
use function Naf\Session\session;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AuthRedirectEventTest extends TestCase
{
    public function testOtherExceptionsAreIgnored(): void
    {
        self::assertNull((new AuthRedirectEvent())->onException(new \RuntimeException('probe')));
    }

    public function testAnonymousAuthFailureRedirectsToLoginAndRemembersTarget(): void
    {
        $request = new ServerRequest('GET', '/admin/pages');
        app()->container()->set(RequestInterface::class, $request);
        app()->container()->set(ServerRequestInterface::class, $request);
        session()->set('userId', null);

        $response = (new AuthRedirectEvent())->onException(new AuthException());

        self::assertNotNull($response);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/admin/login', $response->getHeaderLine('Location'));
        self::assertSame('/admin/pages', session()->getFlash('redirectUri'));
    }
}

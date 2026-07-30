<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use NixPHP\CMS\Events\AuthRedirectEvent;
use NixPHP\CMS\Exceptions\AuthException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use function NixPHP\app;
use function NixPHP\Session\session;

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

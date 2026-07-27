<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use src\Page\Page;
use NixPHP\Exceptions\AbortException;
use NixPHP\Form\Events\CsrfListener;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\NixPHPTestCase;

final class LogoutRouteSecurityTest extends NixPHPTestCase
{
    public function testLogoutIsPostOnly(): void
    {
        $pages = require BASE_PATH . '/app/Resources/pages/auth.php';
        $logout = array_values(array_filter($pages, static fn(Page $page): bool => $page->id() === 'auth.logout'))[0] ?? null;

        self::assertInstanceOf(Page::class, $logout);
        self::assertSame('POST', $logout->method());
        self::assertTrue($logout->requiresAuthentication());
    }

    public function testLogoutPostWithoutCsrfIsRejected(): void
    {
        $request = (new ServerRequest('POST', '/admin/logout'))->withParsedBody([]);

        $this->expectException(AbortException::class);
        (new CsrfListener())->handle($request);
    }
}

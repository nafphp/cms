<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Events\AdminGuardEvent;
use NixPHP\CMS\Exceptions\AuthException;
use NixPHP\CMS\Support\UserHelper;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use function NixPHP\CMS\cms_reset;
use function NixPHP\CMS\user;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminGuardEventTest extends TestCase
{
    protected function tearDown(): void
    {
        cms_reset();
        parent::tearDown();
    }

    public function testIgnoresNonAdminPaths(): void
    {
        (new AdminGuardEvent())->handle(new ServerRequest('GET', '/blog/post'));
        $this->addToAssertionCount(1);
    }

    public function testAllowsLoginPage(): void
    {
        (new AdminGuardEvent())->handle(new ServerRequest('GET', '/admin/login'));
        $this->addToAssertionCount(1);
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->expectException(AuthException::class);
        (new AdminGuardEvent())->handle(new ServerRequest('GET', '/admin/pages'));
    }

    public function testRejectsUserWithoutAdminAccess(): void
    {
        user(new UserHelper(null, [], [], true));
        $this->expectException(AuthException::class);
        (new AdminGuardEvent())->handle(new ServerRequest('GET', '/admin/pages'));
    }

    public function testAllowsUserWithAdminAccess(): void
    {
        user(new UserHelper(null, [Permission::ADMIN_ACCESS], [], true));
        (new AdminGuardEvent())->handle(new ServerRequest('GET', '/admin/pages'));
        $this->addToAssertionCount(1);
    }
}

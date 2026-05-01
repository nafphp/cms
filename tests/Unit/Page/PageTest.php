<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use NixCMS\Enums\Permission;
use NixCMS\Enums\Role;
use NixCMS\Page\Page;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testPageStoresCoreRouteAndAccessMetadata(): void
    {
        $page = Page::create('admin.dashboard')
            ->setPath('admin')
            ->setTitle('Dashboard')
            ->setLayout('admin')
            ->requireAuthentication()
            ->requireRole(Role::ADMIN)
            ->requirePermission(Permission::ADMIN_ACCESS);

        self::assertSame('admin.dashboard', $page->id());
        self::assertSame('/admin', $page->path());
        self::assertSame('Dashboard', $page->title());
        self::assertSame('layouts.admin', $page->layout());
        self::assertTrue($page->requiresAuthentication());
        self::assertSame([Role::ADMIN], $page->requiredRoles());
        self::assertSame([Permission::ADMIN_ACCESS], $page->requiredPermissions());
    }

    public function testMetaIsMutableThroughMetaHelper(): void
    {
        $page = Page::create('custom.about');

        $page->meta()->seo->description = 'About NixCMS';
        $page->meta()->navigation->label = 'About';
        $page->meta()->theme->accent = '#64748b';

        self::assertSame('About NixCMS', $page->meta()->seo->description);
        self::assertSame('About', $page->meta()->navigation->label);
        self::assertSame('#64748b', $page->meta()->theme->accent);
    }

    public function testPageCanRegisterDataHandlerWithPermission(): void
    {
        $handler = static fn() => null;
        $page = Page::create('admin.users.create')
            ->setPath('/admin/users/create')
            ->setDataHandler($handler, 'post', Permission::USERS_CREATE);

        $dataHandler = $page->dataHandler('POST');

        self::assertNotNull($dataHandler);
        self::assertSame('POST', $dataHandler->method());
        self::assertSame($handler, $dataHandler->handler);
        self::assertSame(Permission::USERS_CREATE, $dataHandler->permission);
    }
}

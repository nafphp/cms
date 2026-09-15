<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Naf\CMS\Api\ApiAuthorization;
use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Exceptions\AuthException;
use Naf\CMS\Handlers\Api\Page\DeletePageLayout;
use Naf\CMS\Handlers\Api\Page\StorePageLayout;
use PHPUnit\Framework\TestCase;

final class PageLayoutsApiTest extends TestCase
{
    public function testPageLayoutEndpointsRequirePermissions(): void
    {
        $endpoints = require dirname(__DIR__, 3) . '/src/Resources/api/pages.php';
        $byId      = [];
        foreach ($endpoints as $endpoint) {
            $byId[$endpoint->id()] = $endpoint;
        }

        self::assertSame(StorePageLayout::class, $byId['api.admin.page-layouts.store']->handler());
        self::assertTrue($byId['api.admin.page-layouts.store']->requiresAuthentication());
        self::assertTrue($byId['api.admin.page-layouts.store']->requiresAnyPermission());
        self::assertSame([Permission::PAGES_CREATE, Permission::PAGES_EDIT], $byId['api.admin.page-layouts.store']->requiredPermissions());

        self::assertSame(DeletePageLayout::class, $byId['api.admin.page-layouts.delete']->handler());
        self::assertTrue($byId['api.admin.page-layouts.delete']->requiresAuthentication());
        self::assertSame([Permission::PAGES_DELETE], $byId['api.admin.page-layouts.delete']->requiredPermissions());
    }

    public function testUnauthenticatedLayoutEndpointIsRejected(): void
    {
        $endpoint = ApiEndpoint::delete('api.admin.page-layouts.delete', '/api/admin/page-layouts/{id}')
            ->requirePermission(Permission::PAGES_DELETE)
            ->setHandler(DeletePageLayout::class);

        $this->expectException(AuthException::class);

        (new ApiAuthorization())->authorize($endpoint);
    }
}

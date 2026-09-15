<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Enums\Role;
use PHPUnit\Framework\TestCase;

final class ApiEndpointTest extends TestCase
{
    public function testEndpointStoresRouteAndAccessMetadata(): void
    {
        $handler  = static fn() => null;
        $endpoint = ApiEndpoint::post('api.admin.users.store', 'api/admin/users/store')
            ->requireRole(Role::ADMIN)
            ->requirePermission(Permission::USERS_CREATE)
            ->setHandler($handler);

        self::assertSame('api.admin.users.store', $endpoint->id());
        self::assertSame('POST', $endpoint->method());
        self::assertSame('/api/admin/users/store', $endpoint->path());
        self::assertTrue($endpoint->requiresAuthentication());
        self::assertSame([Role::ADMIN], $endpoint->requiredRoles());
        self::assertSame([Permission::USERS_CREATE], $endpoint->requiredPermissions());
        self::assertSame($handler, $endpoint->handler());
    }

    public function testEndpointCanRequireAnyPermission(): void
    {
        $endpoint = ApiEndpoint::post('api.admin.articles.review', '/api/admin/articles/1/de/review')
            ->requireAnyPermission(Permission::ARTICLES_REVIEW, Permission::ARTICLES_PUBLISH)
            ->setHandler(static fn() => null);

        self::assertTrue($endpoint->requiresAnyPermission());
        self::assertSame([Permission::ARTICLES_REVIEW, Permission::ARTICLES_PUBLISH], $endpoint->requiredPermissions());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Enums\Permission;
use PHPUnit\Framework\TestCase;

final class UserPrivilegeEndpointTest extends TestCase
{
    public function testRoleBearingUserEndpointsRequireUserAndRolePermissions(): void
    {
        $endpoints = require BASE_PATH . '/src/Resources/api/users.php';

        self::assertSame(
            [Permission::USERS_CREATE, Permission::ROLES_MANAGE],
            $this->endpoint($endpoints, 'api.admin.users.store')->requiredPermissions(),
        );
        self::assertSame(
            [Permission::USERS_EDIT, Permission::ROLES_MANAGE],
            $this->endpoint($endpoints, 'api.admin.users.roles')->requiredPermissions(),
        );
        self::assertSame(
            [Permission::USERS_CREATE, Permission::ROLES_MANAGE],
            $this->endpoint($endpoints, 'api.admin.users.invite')->requiredPermissions(),
        );
    }

    public function testRolePermissionMutationRequiresBothManagementPermissions(): void
    {
        $endpoints = require BASE_PATH . '/src/Resources/api/roles.php';

        self::assertSame(
            [Permission::ROLES_MANAGE, Permission::PERMISSIONS_MANAGE],
            $this->endpoint($endpoints, 'api.admin.rbac.roles.permissions')->requiredPermissions(),
        );
    }

    /** @param array<int, ApiEndpoint> $endpoints */
    private function endpoint(array $endpoints, string $id): ApiEndpoint
    {
        foreach ($endpoints as $endpoint) {
            if ($endpoint->id() === $id) {
                return $endpoint;
            }
        }

        self::fail(sprintf('Endpoint "%s" not found.', $id));
    }
}

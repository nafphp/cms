<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Rbac;

use NixPHP\CMS\Handlers\Api\Rbac\CreatePermission;
use NixPHP\CMS\Handlers\Api\Rbac\CreateRole;
use NixPHP\CMS\Handlers\Api\Rbac\DeletePermission;
use NixPHP\CMS\Handlers\Api\Rbac\DeleteRole;
use NixPHP\CMS\Handlers\Api\Rbac\UpdatePermission;
use NixPHP\CMS\Handlers\Api\Rbac\UpdateRole;
use NixPHP\CMS\Handlers\Api\Rbac\UpdateRolePermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

final class RbacHandlersValidationTest extends DatabaseTestCase
{
    /**
     * @param class-string $handler
     */
    #[DataProvider('invalidWriteProvider')]
    public function testWriteHandlersRejectIncompletePayloads(string $handler, int $status): void
    {
        $this->createSchema(
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL)',
            'CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL)',
        );
        JsonRequest::bind([]);
        $response = (new $handler())();

        self::assertSame($status, $response->getStatusCode(), $handler);
        self::assertFalse($this->json($response)['success'], $handler);
    }

    /** @return iterable<string, array{class-string, int}> */
    public static function invalidWriteProvider(): iterable
    {
        yield 'create permission' => [CreatePermission::class, 403];
        yield 'create role' => [CreateRole::class, 403];
        yield 'delete permission' => [DeletePermission::class, 403];
        yield 'delete role' => [DeleteRole::class, 403];
        yield 'update permission' => [UpdatePermission::class, 403];
        yield 'update role' => [UpdateRole::class, 403];
        yield 'update role permissions' => [UpdateRolePermissions::class, 400];
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

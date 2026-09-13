<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Naf\CMS\Mcp\Tools\UserRoleSummaryTool;
use Tests\Support\DatabaseTestCase;

final class UserRoleSummaryToolTest extends DatabaseTestCase
{
    public function testSummarizesRbacTablesWithoutExposingUsers(): void
    {
        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL)',
            'CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL)'
        );

        $adminUser = $this->insertRow('users', ['username' => 'admin']);
        $editorUser = $this->insertRow('users', ['username' => 'editor']);
        $adminRole = $this->insertRow('roles', ['name' => 'admin']);
        $editorRole = $this->insertRow('roles', ['name' => 'editor']);
        $articlesView = $this->insertRow('permissions', ['name' => 'articles.view']);
        $articlesEdit = $this->insertRow('permissions', ['name' => 'articles.edit']);
        $usersView = $this->insertRow('permissions', ['name' => 'users.view']);

        $this->insertRow('user_role', ['user_id' => $adminUser, 'role_id' => $adminRole]);
        $this->insertRow('user_role', ['user_id' => $editorUser, 'role_id' => $editorRole]);
        $this->insertRow('role_permissions', ['role_id' => $adminRole, 'permission_id' => $articlesView]);
        $this->insertRow('role_permissions', ['role_id' => $adminRole, 'permission_id' => $articlesEdit]);
        $this->insertRow('role_permissions', ['role_id' => $adminRole, 'permission_id' => $usersView]);
        $this->insertRow('role_permissions', ['role_id' => $editorRole, 'permission_id' => $articlesView]);

        $result = (new UserRoleSummaryTool($this->pdo))->handle([]);

        self::assertTrue($result['database_available']);
        self::assertSame(2, $result['users']);
        self::assertSame(2, $result['roles']);
        self::assertSame(3, $result['permissions']);
        self::assertSame(2, $result['user_role_assignments']);
        self::assertSame(4, $result['role_permission_assignments']);
        self::assertSame([
            ['name' => 'admin', 'user_count' => 1, 'permission_count' => 3],
            ['name' => 'editor', 'user_count' => 1, 'permission_count' => 1],
        ], $result['roles_detail']);
        self::assertSame(['articles' => 2, 'users' => 1], $result['permissions_by_area']);
    }

    public function testReturnsEmptySummaryWhenSchemaIsMissing(): void
    {
        $result = (new UserRoleSummaryTool($this->pdo))->handle([]);

        self::assertFalse($result['database_available']);
        self::assertSame(0, $result['users']);
        self::assertSame([], $result['roles_detail']);
    }
}

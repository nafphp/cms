<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Security;

use Naf\CMS\Enums\Permission;
use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Services\Security\UserPrivilegePolicy;
use Tests\Support\DatabaseTestCase;

final class UserPrivilegePolicyTest extends DatabaseTestCase
{
    private UserPrivilegePolicy $policy;
    /** @var array<string, int> */
    private array $roles = [];
    /** @var array<string, int> */
    private array $permissions = [];
    /** @var array<string, int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE)',
            'CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, PRIMARY KEY (user_id, role_id))',
            'CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL, PRIMARY KEY (role_id, permission_id))',
        );

        foreach (['admin.access', 'users.create', 'users.edit', 'users.delete', 'roles.manage', 'permissions.manage', 'articles.publish'] as $permission) {
            $this->permissions[$permission] = $this->insertRow('permissions', ['name' => $permission]);
        }

        $this->addRole('admin', array_keys($this->permissions));
        $this->addRole('delegator', array_keys($this->permissions));
        $this->addRole('manager', ['users.create', 'users.edit', 'users.delete', 'roles.manage', 'permissions.manage']);
        $this->addRole('editor', ['users.edit']);
        $this->addRole('publisher', ['articles.publish']);
        $this->addRole('viewer', []);

        $this->addUser('admin', ['admin']);
        $this->addUser('delegator', ['delegator']);
        $this->addUser('manager', ['manager']);
        $this->addUser('editor', ['editor']);
        $this->addUser('viewer', ['viewer']);

        $this->policy = new UserPrivilegePolicy($this->pdo);
    }

    public function testUsersEditAloneCannotAssignAdminCrossUser(): void
    {
        $this->assertDenied('missing_roles_manage', fn() => $this->policy->assertRoleAssignment(
            $this->users['editor'],
            $this->users['manager'],
            ['admin'],
        ));
    }

    public function testNonAdminCannotAssignAdminEvenWhenPermissionScopeMatches(): void
    {
        $this->assertDenied('admin_assignment_requires_admin', fn() => $this->policy->assertRoleAssignment(
            $this->users['delegator'],
            $this->users['editor'],
            ['admin'],
        ));
    }

    public function testActorCannotAssignRoleWithPermissionsOutsideOwnScope(): void
    {
        $this->assertDenied('role_outside_actor_scope', fn() => $this->policy->assertRoleAssignment(
            $this->users['manager'],
            $this->users['editor'],
            ['publisher'],
        ));
    }

    public function testActorCannotChangeOwnRoles(): void
    {
        $this->assertDenied('self_role_change', fn() => $this->policy->assertRoleAssignment(
            $this->users['admin'],
            $this->users['admin'],
            ['admin', 'manager'],
        ));
    }

    public function testLowerPrivilegedActorCannotResetAdminPassword(): void
    {
        $this->assertDenied('higher_privileged_target', fn() => $this->policy->assertPasswordReset(
            $this->users['manager'],
            $this->users['admin'],
        ));
    }

    public function testPasswordResetRequiresUsersEditAtDomainBoundary(): void
    {
        $this->assertDenied('missing_users_edit', fn() => $this->policy->assertPasswordReset(
            $this->users['viewer'],
            $this->users['editor'],
        ));
    }

    public function testDeletionRequiresUsersDeleteAtDomainBoundary(): void
    {
        $this->assertDenied('missing_users_delete', fn() => $this->policy->assertDeletion(
            $this->users['editor'],
            $this->users['viewer'],
        ));
    }

    public function testRoleAssignmentRequiresMatchingUserMutationPermission(): void
    {
        $this->assertDenied('missing_users_create', fn() => $this->policy->assertRoleAssignment(
            $this->users['editor'],
            null,
            ['viewer'],
            Permission::USERS_CREATE,
        ));
    }

    public function testLastAdminCannotLoseRoleOrBeDeleted(): void
    {
        $this->assertDenied('last_admin', fn() => $this->policy->assertRoleAssignment(
            $this->users['admin'],
            $this->users['admin'],
            ['manager'],
        ));
        $this->assertDenied('last_admin', fn() => $this->policy->assertDeletion(
            $this->users['admin'],
            $this->users['admin'],
        ));
    }

    public function testAllowedCrossUserRoleAssignmentPasses(): void
    {
        $this->policy->assertRoleAssignment($this->users['admin'], $this->users['editor'], ['viewer']);
        self::addToAssertionCount(1);
    }

    public function testActorCannotChangePermissionsOfOwnRole(): void
    {
        $this->assertDenied('self_role_permission_change', fn() => $this->policy->assertRolePermissionChange(
            $this->users['manager'],
            $this->roles['manager'],
            [$this->permissions['users.edit']],
        ));
    }

    public function testActorCannotGrantRolePermissionOutsideOwnScope(): void
    {
        $this->assertDenied('permission_outside_actor_scope', fn() => $this->policy->assertRolePermissionChange(
            $this->users['manager'],
            $this->roles['publisher'],
            [$this->permissions['articles.publish']],
        ));
    }

    public function testAllowedRolePermissionChangePasses(): void
    {
        $this->policy->assertRolePermissionChange(
            $this->users['admin'],
            $this->roles['publisher'],
            [$this->permissions['articles.publish']],
        );
        self::addToAssertionCount(1);
    }

    public function testActorCannotRenameOrDeletePermissionGrantedToOwnRole(): void
    {
        $this->assertDenied('self_permission_definition_change', fn() => $this->policy->assertPermissionDefinitionChange(
            $this->users['manager'],
            $this->permissions['users.edit'],
        ));
    }

    public function testUnassignedPermissionDefinitionCanBeManagedWithPermission(): void
    {
        $permissionId = $this->insertRow('permissions', ['name' => 'plugin.unassigned']);
        $this->policy->assertPermissionDefinitionChange($this->users['manager'], $permissionId);
        self::addToAssertionCount(1);
    }

    /** @param array<int, string> $permissionNames */
    private function addRole(string $name, array $permissionNames): void
    {
        $roleId             = $this->insertRow('roles', ['name' => $name]);
        $this->roles[$name] = $roleId;
        foreach ($permissionNames as $permission) {
            $this->insertRow('role_permissions', [
                'role_id'       => $roleId,
                'permission_id' => $this->permissions[$permission],
            ]);
        }
    }

    /** @param array<int, string> $roleNames */
    private function addUser(string $username, array $roleNames): void
    {
        $userId                 = $this->insertRow('users', ['username' => $username]);
        $this->users[$username] = $userId;
        foreach ($roleNames as $role) {
            $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => $this->roles[$role]]);
        }
    }

    private function assertDenied(string $reason, callable $action): void
    {
        try {
            $action();
            self::fail('Expected privileged action to be denied.');
        } catch (PrivilegedActionDenied $exception) {
            self::assertSame($reason, $exception->reason());
        }
    }
}

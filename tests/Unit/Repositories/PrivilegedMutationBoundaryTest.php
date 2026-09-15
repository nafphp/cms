<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Repositories\PermissionRepository;
use Naf\CMS\Repositories\RoleRepository;
use Naf\CMS\Repositories\UserRepository;
use Naf\CMS\Services\Security\CredentialService;
use Naf\ORM\Core\EntityManager;
use PDO;
use Tests\Support\DatabaseTestCase;

final class PrivilegedMutationBoundaryTest extends DatabaseTestCase
{
    private UserRepository $users;
    private RoleRepository $roles;
    private PermissionRepository $permissions;
    /** @var array<string, int> */
    private array $userIds = [];
    /** @var array<string, int> */
    private array $roleIds = [];
    /** @var array<string, int> */
    private array $permissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL, password TEXT NOT NULL, email TEXT NULL, firstname TEXT NULL, lastname TEXT NULL)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE)',
            'CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, PRIMARY KEY (user_id, role_id))',
            'CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL, PRIMARY KEY (role_id, permission_id))',
            'CREATE TABLE sessions (id TEXT PRIMARY KEY, user_id INTEGER NULL)',
        );

        foreach (['users.edit', 'users.delete', 'roles.manage', 'permissions.manage', 'articles.publish'] as $permission) {
            $this->permissionIds[$permission] = $this->insertRow('permissions', ['name' => $permission]);
        }

        $this->addRole('admin', array_keys($this->permissionIds));
        $this->addRole('manager', ['users.edit', 'users.delete', 'roles.manage', 'permissions.manage']);
        $this->addRole('editor', ['users.edit']);
        $this->addRole('publisher', ['articles.publish']);
        $this->addRole('viewer', []);

        $this->addUser('admin', 'admin-hash', 'admin');
        $this->addUser('manager', 'manager-hash', 'manager');
        $this->addUser('editor', 'editor-hash', 'editor');

        $entityManager     = new EntityManager($this->pdo);
        $this->users       = new UserRepository($this->pdo, $entityManager, new CredentialService());
        $this->roles       = new RoleRepository($this->pdo, $entityManager);
        $this->permissions = new PermissionRepository($this->pdo, $entityManager);
    }

    public function testRepositoryRejectsRoleAssignmentOutsideActorScope(): void
    {
        $this->assertDenied('role_outside_actor_scope', fn() => $this->users->updateRolesByUserId(
            $this->userIds['manager'],
            $this->userIds['editor'],
            ['publisher'],
        ));

        self::assertSame(['editor'], $this->roleNamesFor('editor'));
    }

    public function testRepositoryRejectsPrivilegedPasswordReset(): void
    {
        $this->assertDenied('higher_privileged_target', fn() => $this->users->resetPasswordByActor(
            $this->userIds['manager'],
            $this->userIds['admin'],
            'replacement-password',
        ));

        self::assertSame('admin-hash', $this->passwordFor('admin'));
    }

    public function testRepositoryRejectsWeakPasswordEvenForAllowedTarget(): void
    {
        self::assertFalse($this->users->resetPasswordByActor(
            $this->userIds['manager'],
            $this->userIds['editor'],
            'short',
        ));

        self::assertSame('editor-hash', $this->passwordFor('editor'));
    }

    public function testRepositoryRejectsDeletingLastAdmin(): void
    {
        $this->assertDenied('last_admin', fn() => $this->users->deleteById(
            $this->userIds['manager'],
            $this->userIds['admin'],
        ));

        self::assertSame(1, (int) $this->fetchOne(
            'SELECT COUNT(*) AS total FROM users WHERE id = :id',
            ['id' => $this->userIds['admin']],
        )['total']);
    }

    public function testRoleRepositoryRejectsPermissionsOutsideActorScope(): void
    {
        $this->assertDenied('permission_outside_actor_scope', fn() => $this->roles->updatePermissionsByRoleId(
            $this->userIds['manager'],
            $this->roleIds['publisher'],
            [$this->permissionIds['articles.publish']],
        ));

        self::assertSame(['articles.publish'], $this->permissionNamesFor('publisher'));
    }

    public function testRoleRepositoryCannotDeleteLastAdminRole(): void
    {
        $this->assertDenied('last_admin', fn() => $this->roles->deleteById(
            $this->userIds['manager'],
            $this->roleIds['admin'],
        ));

        self::assertSame(1, (int) $this->fetchOne(
            'SELECT COUNT(*) AS total FROM roles WHERE id = :id',
            ['id' => $this->roleIds['admin']],
        )['total']);
    }

    public function testPermissionRepositoryRejectsSelfEscalationByRename(): void
    {
        $this->assertDenied('self_permission_definition_change', fn() => $this->permissions->renameById(
            $this->userIds['manager'],
            $this->permissionIds['users.edit'],
            'themes.code.manage',
        ));

        self::assertSame('users.edit', (string) $this->pdo->query(
            'SELECT name FROM permissions WHERE id = ' . $this->permissionIds['users.edit'],
        )->fetchColumn());
    }

    public function testBootstrapCreationStopsAfterFirstAccountExists(): void
    {
        self::assertNull($this->users->createBootstrapUserWithRoles(
            'second-admin',
            'secret-password',
            null,
            null,
            ['admin'],
            'second@example.test',
        ));
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testBootstrapCreationCannotStartWithUnprivilegedRole(): void
    {
        $this->pdo->exec('DELETE FROM user_role');
        $this->pdo->exec('DELETE FROM users');

        self::assertNull($this->users->createBootstrapUserWithRoles(
            'first-viewer',
            'secret-password',
            null,
            null,
            ['viewer'],
            'viewer@example.test',
        ));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testBootstrapAndInviteRegistrationRejectWeakPasswordsAtRepositoryBoundary(): void
    {
        $this->pdo->exec('DELETE FROM user_role');
        $this->pdo->exec('DELETE FROM users');

        self::assertNull($this->users->createBootstrapUserWithRoles(
            'first-admin',
            'short',
            null,
            null,
            ['admin'],
            'admin@example.test',
        ));
        self::assertSame(
            ['user' => null, 'reason' => 'invalid_password'],
            $this->users->registerUserWithInvite('token', 'invite@example.test', 'invitee', 'short', null, null),
        );
    }

    /** @param array<int, string> $permissionNames */
    private function addRole(string $name, array $permissionNames): void
    {
        $roleId               = $this->insertRow('roles', ['name' => $name]);
        $this->roleIds[$name] = $roleId;
        foreach ($permissionNames as $permission) {
            $this->insertRow('role_permissions', [
                'role_id'       => $roleId,
                'permission_id' => $this->permissionIds[$permission],
            ]);
        }
    }

    private function addUser(string $username, string $password, string $role): void
    {
        $userId                   = $this->insertRow('users', ['username' => $username, 'password' => $password]);
        $this->userIds[$username] = $userId;
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => $this->roleIds[$role]]);
    }

    /** @return array<int, string> */
    private function roleNamesFor(string $username): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.name FROM user_role ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id ORDER BY r.name',
        );
        $statement->execute(['id' => $this->userIds[$username]]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, string> */
    private function permissionNamesFor(string $role): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :id ORDER BY p.name',
        );
        $statement->execute(['id' => $this->roleIds[$role]]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function passwordFor(string $username): string
    {
        return (string) $this->fetchOne(
            'SELECT password FROM users WHERE id = :id',
            ['id' => $this->userIds[$username]],
        )['password'];
    }

    private function assertDenied(string $reason, callable $action): void
    {
        try {
            $action();
            self::fail('Expected privileged repository mutation to be denied.');
        } catch (PrivilegedActionDenied $exception) {
            self::assertSame($reason, $exception->reason());
        }
    }
}

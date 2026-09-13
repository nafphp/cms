<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Naf\CMS\Models\Role;
use Naf\CMS\Repositories\RoleRepository;
use Naf\ORM\Core\EntityManager;
use Tests\Support\DatabaseTestCase;

final class RoleRepositoryTest extends DatabaseTestCase
{
    private RoleRepository $repository;
    private int $actorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            'CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                createdAt TEXT DEFAULT CURRENT_TIMESTAMP
            )',
            'CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            )',
            'CREATE TABLE role_permissions (
                role_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, permission_id)
            )',
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL)',
            'CREATE TABLE user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, role_id)
            )',
        );

        $permissionId = $this->insertRow('permissions', ['name' => 'roles.manage']);
        $roleId = $this->insertRow('roles', ['name' => 'security-admin']);
        $this->insertRow('role_permissions', ['role_id' => $roleId, 'permission_id' => $permissionId]);
        $this->actorId = $this->insertRow('users', ['username' => 'actor']);
        $this->insertRow('user_role', ['user_id' => $this->actorId, 'role_id' => $roleId]);

        $this->repository = new RoleRepository($this->pdo, new EntityManager($this->pdo));
    }

    public function testCreateByNamePersistsNormalizedRole(): void
    {
        $role = $this->repository->createByName($this->actorId, '  Managing Editor  ');

        self::assertInstanceOf(Role::class, $role);
        self::assertSame('managing editor', $role->getName());
        self::assertGreaterThan(0, $role->getId());

        self::assertSame(
            ['id' => $role->getId(), 'name' => 'managing editor'],
            $this->fetchOne('SELECT id, name FROM roles WHERE id = :id', ['id' => $role->getId()])
        );
    }

    public function testCreateByNameRejectsDuplicateRole(): void
    {
        self::assertInstanceOf(Role::class, $this->repository->createByName($this->actorId, 'Publisher'));

        self::assertNull($this->repository->createByName($this->actorId, ' publisher '));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
    }

    public function testCreateByNameRequiresRolesManageAtRepositoryBoundary(): void
    {
        $viewerId = $this->insertRow('users', ['username' => 'viewer']);

        $this->expectException(\Naf\CMS\Exceptions\PrivilegedActionDenied::class);
        $this->repository->createByName($viewerId, 'forbidden');
    }
}

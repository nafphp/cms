<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use NixCMS\Models\Role;
use NixCMS\Repositories\RoleRepository;
use NixPHP\ORM\Core\EntityManager;
use Tests\Support\DatabaseTestCase;

final class RoleRepositoryTest extends DatabaseTestCase
{
    private RoleRepository $repository;

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
        );

        $this->repository = new RoleRepository($this->pdo, new EntityManager($this->pdo));
    }

    public function testCreateByNamePersistsNormalizedRole(): void
    {
        $role = $this->repository->createByName('  Managing Editor  ');

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
        self::assertInstanceOf(Role::class, $this->repository->createByName('Publisher'));

        self::assertNull($this->repository->createByName(' publisher '));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
    }
}

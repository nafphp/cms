<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Naf\CMS\Repositories\UserRepository;
use Naf\CMS\Services\Security\CredentialService;
use Naf\ORM\Core\EntityManager;
use PDO;
use Tests\Support\DatabaseTestCase;

final class UserReadQueriesTest extends DatabaseTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('NAFCMS_MYSQL_READ_TEST') === '1') {
            $this->pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')),
                (string) getenv('DB_USERNAME'),
                (string) getenv('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
            );
        }
        $create = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'CREATE TEMPORARY TABLE' : 'CREATE TABLE';
        $this->createSchema(
            "$create users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, firstname TEXT, lastname TEXT, createdAt TEXT)",
            "$create roles (id INTEGER PRIMARY KEY, name TEXT)",
            "$create permissions (id INTEGER PRIMARY KEY, name TEXT)",
            "$create user_role (user_id INTEGER, role_id INTEGER)",
            "$create role_permissions (role_id INTEGER, permission_id INTEGER)",
        );
        $this->insertRow('users', ['id' => 1, 'username' => 'alice']);
        $this->insertRow('users', ['id' => 2, 'username' => 'bob']);
        $this->insertRow('roles', ['id' => 1, 'name' => 'reviewer']);
        $this->insertRow('roles', ['id' => 2, 'name' => 'editor']);
        $this->insertRow('permissions', ['id' => 1, 'name' => 'users.view']);
        $this->insertRow('permissions', ['id' => 2, 'name' => 'articles.view']);
        $this->insertRow('permissions', ['id' => 3, 'name' => 'articles.edit']);
        $this->insertRow('user_role', ['user_id' => 1, 'role_id' => 1]);
        $this->insertRow('user_role', ['user_id' => 1, 'role_id' => 2]);
        foreach ([[1, 1], [1, 2], [2, 2], [2, 3]] as [$role, $permission]) {
            $this->insertRow('role_permissions', ['role_id' => $role, 'permission_id' => $permission]);
        }
        $this->repository = new UserRepository($this->pdo, new EntityManager($this->pdo), new CredentialService());
    }

    public function testUserListReturnsSortedDistinctRolesAndPermissions(): void
    {
        $rows = $this->repository->findAllWithRolesAndPermissions();

        self::assertSame(['alice', 'bob'], array_column($rows, 'username'));
        self::assertSame(['editor', 'reviewer'], $rows[0]['roles']);
        self::assertSame(['articles.edit', 'articles.view', 'users.view'], $rows[0]['permissions']);
        self::assertSame([], $rows[1]['roles']);
        self::assertSame([], $rows[1]['permissions']);
        self::assertSame('', $rows[0]['email']);
    }

    public function testUserFormReturnsSortedRolesAndHandlesMissingAccounts(): void
    {
        $record = $this->repository->findOneForForm(1);

        self::assertNotNull($record);
        self::assertSame('alice', $record['username']);
        self::assertSame(['editor', 'reviewer'], $record['roles']);
        self::assertSame([], $this->repository->findOneForForm(2)['roles']);
        self::assertNull($this->repository->findOneForForm(999));
        self::assertNull($this->repository->findOneForForm(0));
    }
}

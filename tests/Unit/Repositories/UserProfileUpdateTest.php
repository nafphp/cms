<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Naf\CMS\Repositories\UserRepository;
use Naf\CMS\Services\Security\CredentialService;
use Naf\ORM\Core\EntityManager;
use Tests\Support\DatabaseTestCase;

final class UserProfileUpdateTest extends DatabaseTestCase
{
    public function testProfileUpdateDoesNotChangeRolesOrPassword(): void
    {
        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL, password TEXT NOT NULL, email TEXT NULL, firstname TEXT NULL, lastname TEXT NULL)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE user_role (user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, PRIMARY KEY (user_id, role_id))',
        );
        $userId = $this->insertRow('users', [
            'username'  => 'before',
            'password'  => 'existing-hash',
            'email'     => null,
            'firstname' => null,
            'lastname'  => null,
        ]);
        $roleId = $this->insertRow('roles', ['name' => 'editor']);
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => $roleId]);

        $repository = new UserRepository($this->pdo, new EntityManager($this->pdo), new CredentialService());
        self::assertTrue($repository->updateProfile($userId, 'after', 'Ada', 'Editor', 'ada@example.test'));

        self::assertSame(
            ['username' => 'after', 'password' => 'existing-hash', 'email' => 'ada@example.test'],
            $this->fetchOne('SELECT username, password, email FROM users WHERE id = :id', ['id' => $userId]),
        );
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM user_role')->fetchColumn());
    }
}

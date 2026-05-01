<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Repositories\RoleRepository;
use NixCMS\Repositories\UserRepository;
use function NixPHP\ORM\repo;
use function NixPHP\redirect;
use function NixPHP\View\render;

$roleDefinitions = static fn(): array => repo(RoleRepository::class)->findAllWithPermissions();

return [
    Page::create('admin.users')
        ->setPath('/admin/users')
        ->setTitle('Users')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_VIEW)
        ->setContent(static fn() => render('admin.users.index', [
            'title' => 'Users',
            'users' => repo(UserRepository::class)->findAllWithRolesAndPermissions(),
            'roleDefinitions' => $roleDefinitions(),
        ])),

    Page::create('admin.users.create')
        ->setPath('/admin/users/create')
        ->setTitle('Create user')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_CREATE)
        ->setContent(static fn() => render('admin.users.form', [
            'title' => 'Create user',
            'roleDefinitions' => $roleDefinitions(),
            'user' => null,
        ])),

    Page::create('admin.users.edit')
        ->setPath('/admin/users/{userId}/edit')
        ->setTitle('Edit user')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_EDIT)
        ->setContent(static function (string $userId) use ($roleDefinitions) {
            $user = repo(UserRepository::class)->findOneForForm((int) $userId);
            if ($user === null) {
                return redirect('/admin/users');
            }

            return render('admin.users.form', [
                'title' => 'Edit user',
                'roleDefinitions' => $roleDefinitions(),
                'user' => $user,
            ]);
        }),
];

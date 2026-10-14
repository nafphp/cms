<?php

declare(strict_types=1);

use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;
use Naf\CMS\Repositories\RoleRepository;
use Naf\CMS\Repositories\UserRepository;

use function Naf\CMS\user;
use function Naf\I18n\t;
use function Naf\ORM\repo;
use function Naf\redirect;
use function Naf\View\render;

$roleDefinitions = static fn(): array => user()?->can(Permission::ROLES_MANAGE) === true
    ? repo(RoleRepository::class)->findAllWithPermissions()
    : [];

return [
    Page::create('admin.users')
        ->setPath('/admin/users')
        ->setTitle('Users')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_VIEW)
        ->setAdminNavigation('Users', icon: 'user', order: 30, key: 'users')
        ->setContent(static fn() => render('admin.users.index', [
            'title'           => t('Users'),
            'pageTitle'       => t('Users'),
            'pageSubtitle'    => t('Invite users and manage roles.'),
            'users'           => repo(UserRepository::class)->findAllWithRolesAndPermissions(),
            'roleDefinitions' => $roleDefinitions(),
        ])),

    Page::create('admin.users.create')
        ->setPath('/admin/users/create')
        ->setTitle('Create user')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_CREATE)
        ->requirePermission(Permission::ROLES_MANAGE)
        ->hideFromAdminNavigation('users')
        ->setContent(static fn() => render('admin.users.form', [
            'title'           => t('Create user'),
            'pageTitle'       => t('Create user'),
            'pageSubtitle'    => t('Create an account and assign initial roles.'),
            'roleDefinitions' => $roleDefinitions(),
            'canManageRoles'  => true,
            'user'            => null,
        ])),

    Page::create('admin.users.edit')
        ->setPath('/admin/users/{userId}/edit')
        ->setTitle('Edit user')
        ->setLayout('admin')
        ->requirePermission(Permission::USERS_EDIT)
        ->hideFromAdminNavigation('users')
        ->setContent(static function (string $userId) {
            $user = repo(UserRepository::class)->findOneForForm((int) $userId);
            if ($user === null) {
                return redirect('/admin/users');
            }

            return render('admin.users.form', [
                'title'           => t('Edit user'),
                'pageTitle'       => t('Edit user'),
                'pageSubtitle'    => t('Update account details.'),
                'roleDefinitions' => [],
                'canManageRoles'  => false,
                'user'            => $user,
            ]);
        }),
];

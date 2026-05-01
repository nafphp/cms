<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Repositories\PermissionRepository;
use NixCMS\Repositories\RoleRepository;
use function NixPHP\I18n\t;
use function NixPHP\ORM\repo;
use function NixPHP\View\render;

return [
    Page::create('admin.roles')
        ->setPath('/admin/roles')
        ->setTitle('Role management')
        ->setLayout('admin')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setAdminNavigation('Roles & Permissions', icon: 'lock', order: 40, key: 'roles')
        ->setContent(static function () {
            repo(PermissionRepository::class)->mergeDuplicateNames();

            return render('admin.roles.index', [
                'title' => t('Role management'),
                'pageTitle' => t('Role management'),
                'pageSubtitle' => t('Manage roles and assign permissions.'),
                'roles' => repo(RoleRepository::class)->findAllWithPermissions(),
                'permissions' => repo(PermissionRepository::class)->findAllWithRoleCount(),
            ]);
        }),
];

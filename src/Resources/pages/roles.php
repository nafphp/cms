<?php

declare(strict_types=1);

use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;
use Naf\CMS\Repositories\PermissionRepository;
use Naf\CMS\Repositories\RoleRepository;
use function Naf\I18n\t;
use function Naf\ORM\repo;
use function Naf\View\render;

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

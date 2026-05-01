<?php

declare(strict_types=1);

use NixCMS\Enums\Permission;
use NixCMS\Page\Page;
use NixCMS\Repositories\PermissionRepository;
use NixCMS\Repositories\RoleRepository;
use function NixPHP\ORM\repo;
use function NixPHP\View\render;

return [
    Page::create('admin.roles')
        ->setPath('/admin/roles')
        ->setTitle('Rollenverwaltung')
        ->setLayout('admin')
        ->requirePermission(Permission::ROLES_MANAGE)
        ->setContent(static function () {
            repo(PermissionRepository::class)->mergeDuplicateNames();

            return render('admin.roles.index', [
                'title' => 'Rollenverwaltung',
                'roles' => repo(RoleRepository::class)->findAllWithPermissions(),
                'permissions' => repo(PermissionRepository::class)->findAllWithRoleCount(),
            ]);
        }),
];

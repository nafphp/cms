<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\AbstractController;
use Cms\Enums\Permission;
use Cms\Repositories\PermissionRepository;
use Cms\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\ORM\repo;
use function NixPHP\View\render;

final class RoleController extends AbstractController
{
    public function index(): ResponseInterface
    {
        $this->requirePermission(Permission::ROLES_MANAGE);
        repo(PermissionRepository::class)->mergeDuplicateNames();

        return render('admin.roles.index', [
            'title' => 'Rollenverwaltung',
            'roles' => repo(RoleRepository::class)->findAllWithPermissions(),
            'permissions' => repo(PermissionRepository::class)->findAllWithRoleCount(),
        ]);
    }
}

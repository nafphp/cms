<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\AbstractController;
use Cms\Enums\Permission;
use Cms\Repositories\RoleRepository;
use Cms\Repositories\UserRepository;
use Cms\Services\Security\RbacCatalogService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\ORM\repo;
use function NixPHP\redirect;
use function NixPHP\View\render;

final class UserController extends AbstractController
{
    public function index(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_VIEW);

        return render('admin.users.index', [
            'title' => 'Users',
            'users' => repo(UserRepository::class)->findAllWithRolesAndPermissions(),
            'roleDefinitions' => $this->roleDefinitions(),
        ]);
    }

    public function create(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_CREATE);

        return render('admin.users.form', [
            'title' => 'Create user',
            'roleDefinitions' => $this->roleDefinitions(),
            'user' => null,
        ]);
    }

    public function edit(string $userId): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_EDIT);

        $user = repo(UserRepository::class)->findOneForForm((int) $userId);
        if ($user === null) {
            return redirect('/admin/users');
        }

        return render('admin.users.form', [
            'title' => 'Edit user',
            'roleDefinitions' => $this->roleDefinitions(),
            'user' => $user,
        ]);
    }

    /**
     * @return array<int, array{id:int,name:string,permission_names:array<int,string>}>
     */
    private function roleDefinitions(): array
    {
        $roles = repo(RoleRepository::class)->findAllWithPermissions();

        return $roles !== [] ? $roles : (new RbacCatalogService())->roles();
    }
}

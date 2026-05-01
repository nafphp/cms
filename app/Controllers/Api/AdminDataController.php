<?php

declare(strict_types=1);

namespace Cms\Controllers\Api;

use Cms\Enums\Permission;
use Cms\Exceptions\AuthException;
use Cms\Repositories\UserRepository;
use Cms\Services\Admin\AdminDashboardService;
use Cms\Services\Content\ArticleCatalogService;
use Cms\Services\Security\RbacCatalogService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class AdminDataController
{
    public function summary(): ResponseInterface
    {
        return json((new AdminDashboardService())->summary());
    }

    public function users(): ResponseInterface
    {
        if (\user()?->can(Permission::USERS_VIEW) !== true) {
            throw new AuthException();
        }

        return json(repo(UserRepository::class)->findAllWithRolesAndPermissions());
    }

    public function roles(): ResponseInterface
    {
        $catalog = new RbacCatalogService();

        return json([
            'roles' => $catalog->roles(),
            'permissions' => $catalog->permissions(),
        ]);
    }

    public function articles(): ResponseInterface
    {
        if (\user()?->can(Permission::ARTICLES_VIEW) !== true) {
            throw new AuthException();
        }

        return json((new ArticleCatalogService())->overview()['articles']);
    }
}

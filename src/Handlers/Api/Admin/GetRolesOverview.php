<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Admin;

use NixPHP\CMS\Repositories\PermissionRepository;
use NixPHP\CMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class GetRolesOverview
{
    public function __invoke(): ResponseInterface
    {
        return json([
            'roles' => repo(RoleRepository::class)->findAllWithPermissions(),
            'permissions' => repo(PermissionRepository::class)->findAllWithRoleCount(),
        ]);
    }
}

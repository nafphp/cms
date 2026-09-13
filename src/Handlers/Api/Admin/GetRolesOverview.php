<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Admin;

use Naf\CMS\Repositories\PermissionRepository;
use Naf\CMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;
use function Naf\ORM\repo;

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

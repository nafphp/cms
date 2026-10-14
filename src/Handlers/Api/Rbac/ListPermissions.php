<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Rbac;

use Naf\CMS\Repositories\PermissionRepository;
use Psr\Http\Message\ResponseInterface;

use function Naf\json;
use function Naf\ORM\repo;

final class ListPermissions
{
    public function __invoke(): ResponseInterface
    {
        repo(PermissionRepository::class)->mergeDuplicateNames();

        return json(repo(PermissionRepository::class)->findAllWithRoleCount());
    }
}

<?php

declare(strict_types=1);

namespace src\Handlers\Api\Rbac;

use src\Repositories\PermissionRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class ListPermissions
{
    public function __invoke(): ResponseInterface
    {
        repo(PermissionRepository::class)->mergeDuplicateNames();

        return json(repo(PermissionRepository::class)->findAllWithRoleCount());
    }
}

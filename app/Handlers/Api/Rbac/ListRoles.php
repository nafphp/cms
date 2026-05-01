<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class ListRoles
{
    public function __invoke(): ResponseInterface
    {
        return json(repo(RoleRepository::class)->findAllWithPermissions());
    }
}

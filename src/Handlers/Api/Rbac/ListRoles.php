<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Rbac;

use NixPHP\CMS\Repositories\RoleRepository;
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

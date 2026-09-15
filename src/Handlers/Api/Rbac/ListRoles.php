<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Rbac;

use Naf\CMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;

use function Naf\json;
use function Naf\ORM\repo;

final class ListRoles
{
    public function __invoke(): ResponseInterface
    {
        return json(repo(RoleRepository::class)->findAllWithPermissions());
    }
}

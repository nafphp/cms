<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Admin;

use Naf\CMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;

use function Naf\json;
use function Naf\ORM\repo;

final class ListUsers
{
    public function __invoke(): ResponseInterface
    {
        return json(repo(UserRepository::class)->findAllWithRolesAndPermissions());
    }
}

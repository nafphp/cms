<?php

declare(strict_types=1);

namespace src\Handlers\Api\Admin;

use src\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class ListUsers
{
    public function __invoke(): ResponseInterface
    {
        return json(repo(UserRepository::class)->findAllWithRolesAndPermissions());
    }
}

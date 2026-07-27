<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\User;

use NixPHP\CMS\Repositories\UserRepository;
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

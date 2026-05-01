<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Models\Role;
use NixCMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class CreateRole
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $created = repo(RoleRepository::class)->createByName((string) ($this->jsonBody()['name'] ?? ''));
        if (!$created instanceof Role) {
            return json(['success' => false, 'reason' => 'Could not create role.'], 400);
        }

        return json([
            'success' => true,
            'role' => [
                'id' => $created->getId(),
                'name' => $created->getName(),
                'permission_names' => [],
            ],
        ]);
    }
}

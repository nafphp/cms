<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Models\Permission;
use NixCMS\Repositories\PermissionRepository;
use NixCMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class CreatePermission
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $created = repo(PermissionRepository::class)->createByName((string) ($this->jsonBody()['name'] ?? ''));
        if (!$created instanceof Permission) {
            return json(['success' => false, 'reason' => 'Could not create permission.'], 400);
        }

        AuditEntry::create(
            'permission.created',
            'permission',
            (string) $created->getId(),
            null,
            ['id' => $created->getId(), 'name' => $created->getName(), 'role_count' => 0],
        )->record();

        return json([
            'success' => true,
            'permission' => [
                'id' => $created->getId(),
                'name' => $created->getName(),
                'role_count' => 0,
            ],
        ]);
    }
}

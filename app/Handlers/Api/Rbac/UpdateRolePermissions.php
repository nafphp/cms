<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class UpdateRolePermissions
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $payload = $this->jsonBody();
        $roleId = (int) ($payload['roleId'] ?? 0);
        $permissionIds = $payload['permissionIds'] ?? [];
        if ($roleId <= 0 || !is_array($permissionIds)) {
            return json(['success' => false, 'reason' => 'Invalid role assignment payload.'], 400);
        }

        $permissionNames = repo(RoleRepository::class)->updatePermissionsByRoleId($roleId, $permissionIds);
        $updatedRole = null;
        foreach (repo(RoleRepository::class)->findAllWithPermissions() as $role) {
            if ((int) ($role['id'] ?? 0) === $roleId) {
                $updatedRole = $role;
                break;
            }
        }

        return json([
            'success' => true,
            'permissionNames' => $permissionNames,
            'role' => $updatedRole,
        ]);
    }
}

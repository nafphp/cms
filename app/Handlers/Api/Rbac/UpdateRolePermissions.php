<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\RoleRepository;
use NixCMS\Support\AuditEntry;
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

        $repository = repo(RoleRepository::class);
        $oldRole = null;
        foreach ($repository->findAllWithPermissions() as $role) {
            if ((int) ($role['id'] ?? 0) === $roleId) {
                $oldRole = $role;
                break;
            }
        }

        $permissionNames = $repository->updatePermissionsByRoleId($roleId, $permissionIds);
        $updatedRole = null;
        foreach ($repository->findAllWithPermissions() as $role) {
            if ((int) ($role['id'] ?? 0) === $roleId) {
                $updatedRole = $role;
                break;
            }
        }

        AuditEntry::create(
            'role.permissions_updated',
            'role',
            (string) $roleId,
            $oldRole,
            $updatedRole,
        )->record();

        return json([
            'success' => true,
            'permissionNames' => $permissionNames,
            'role' => $updatedRole,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace src\Handlers\Api\Rbac;

use src\Exceptions\PrivilegedActionDenied;
use src\Handlers\Support\ReadsJsonBody;
use src\Handlers\Support\RejectsPrivilegedAction;
use src\Repositories\RoleRepository;
use src\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixCMS\user;

final class UpdateRolePermissions
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

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

        try {
            $permissionNames = $repository->updatePermissionsByRoleId(
                (int) user()?->get()?->getId(),
                $roleId,
                $permissionIds,
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'role.permissions.update', 'role', (string) $roleId);
        }
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

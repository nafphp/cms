<?php

declare(strict_types=1);

namespace Cms\Controllers\Api;

use Cms\Controllers\AbstractController;
use Cms\Enums\Permission;
use Cms\Models\Role;
use Cms\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\request;

final class RoleApiController extends AbstractController
{
    public function list(): ResponseInterface
    {
        $this->requirePermission(Permission::ROLES_MANAGE);

        return json(repo(RoleRepository::class)->findAllWithPermissions());
    }

    public function create(): ResponseInterface
    {
        $this->requirePermission(Permission::ROLES_MANAGE);

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

    public function update(): ResponseInterface
    {
        $this->requirePermission(Permission::ROLES_MANAGE);

        $payload = $this->jsonBody();
        $updated = repo(RoleRepository::class)->renameById(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['name'] ?? ''),
        );

        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update role.'], 400);
        }

        return json(['success' => true]);
    }

    public function delete(): ResponseInterface
    {
        $this->requirePermission(Permission::ROLES_MANAGE);

        $deleted = repo(RoleRepository::class)->deleteById((int) ($this->jsonBody()['id'] ?? 0));
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete role.'], 400);
        }

        return json(['success' => true]);
    }

    public function updatePermissions(): ResponseInterface
    {
        $this->requirePermission(Permission::ROLES_MANAGE);

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

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $data = json_decode((string) request()->getBody(), true);

        return is_array($data) ? $data : [];
    }
}

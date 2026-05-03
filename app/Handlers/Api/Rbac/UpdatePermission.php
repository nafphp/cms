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

final class UpdatePermission
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $payload = $this->jsonBody();
        $permissionId = (int) ($payload['id'] ?? 0);
        $repository = repo(PermissionRepository::class);
        /** @var Permission|null $old */
        $old = $repository->findOneBy('id', $permissionId);
        $updated = $repository->renameById(
            $permissionId,
            (string) ($payload['name'] ?? ''),
        );

        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update permission.'], 400);
        }

        /** @var Permission|null $new */
        $new = $repository->findOneBy('id', $permissionId);
        AuditEntry::create(
            'permission.updated',
            'permission',
            (string) $permissionId,
            $this->permissionAuditSnapshot($old),
            $this->permissionAuditSnapshot($new),
        )->record();

        return json(['success' => true]);
    }

    /** @return array<string, mixed>|null */
    private function permissionAuditSnapshot(?Permission $permission): ?array
    {
        if (!$permission instanceof Permission) {
            return null;
        }

        return ['id' => $permission->getId(), 'name' => $permission->getName()];
    }
}

<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Rbac;

use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Handlers\Support\RejectsPrivilegedAction;
use Naf\CMS\Models\Permission;
use Naf\CMS\Repositories\PermissionRepository;
use Naf\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;
use function Naf\ORM\repo;
use function Naf\CMS\user;

final class UpdatePermission
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $payload = $this->jsonBody();
        $permissionId = (int) ($payload['id'] ?? 0);
        $repository = repo(PermissionRepository::class);
        /** @var Permission|null $old */
        $old = $repository->findOneBy('id', $permissionId);
        try {
            $updated = $repository->renameById(
                (int) user()?->get()?->getId(),
                $permissionId,
                (string) ($payload['name'] ?? ''),
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'permission.update', 'permission', (string) $permissionId);
        }

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

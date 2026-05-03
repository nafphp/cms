<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Models\Role;
use NixCMS\Repositories\RoleRepository;
use NixCMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class UpdateRole
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $payload = $this->jsonBody();
        $roleId = (int) ($payload['id'] ?? 0);
        $repository = repo(RoleRepository::class);
        /** @var Role|null $old */
        $old = $repository->findOneBy('id', $roleId);
        $updated = $repository->renameById(
            $roleId,
            (string) ($payload['name'] ?? ''),
        );

        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update role.'], 400);
        }

        /** @var Role|null $new */
        $new = $repository->findOneBy('id', $roleId);
        AuditEntry::create(
            'role.updated',
            'role',
            (string) $roleId,
            $this->roleAuditSnapshot($old),
            $this->roleAuditSnapshot($new),
        )->record();

        return json(['success' => true]);
    }

    /** @return array<string, mixed>|null */
    private function roleAuditSnapshot(?Role $role): ?array
    {
        if (!$role instanceof Role) {
            return null;
        }

        return ['id' => $role->getId(), 'name' => $role->getName()];
    }
}

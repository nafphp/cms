<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Rbac;

use NixPHP\CMS\Exceptions\PrivilegedActionDenied;
use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Handlers\Support\RejectsPrivilegedAction;
use NixPHP\CMS\Models\Role;
use NixPHP\CMS\Repositories\RoleRepository;
use NixPHP\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\CMS\user;

final class UpdateRole
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $payload = $this->jsonBody();
        $roleId = (int) ($payload['id'] ?? 0);
        $repository = repo(RoleRepository::class);
        /** @var Role|null $old */
        $old = $repository->findOneBy('id', $roleId);
        try {
            $updated = $repository->renameById(
                (int) user()?->get()?->getId(),
                $roleId,
                (string) ($payload['name'] ?? ''),
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'role.update', 'role', (string) $roleId);
        }

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

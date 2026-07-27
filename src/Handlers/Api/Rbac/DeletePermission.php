<?php

declare(strict_types=1);

namespace src\Handlers\Api\Rbac;

use src\Exceptions\PrivilegedActionDenied;
use src\Handlers\Support\ReadsJsonBody;
use src\Handlers\Support\RejectsPrivilegedAction;
use src\Models\Permission;
use src\Repositories\PermissionRepository;
use src\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixCMS\user;

final class DeletePermission
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $permissionId = (int) ($this->jsonBody()['id'] ?? 0);
        $repository = repo(PermissionRepository::class);
        /** @var Permission|null $old */
        $old = $repository->findOneBy('id', $permissionId);
        try {
            $deleted = $repository->deleteById((int) user()?->get()?->getId(), $permissionId);
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'permission.delete', 'permission', (string) $permissionId);
        }
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete permission.'], 400);
        }

        AuditEntry::create(
            'permission.deleted',
            'permission',
            (string) $permissionId,
            $old instanceof Permission ? ['id' => $old->getId(), 'name' => $old->getName()] : null,
        )->record();

        return json(['success' => true]);
    }
}

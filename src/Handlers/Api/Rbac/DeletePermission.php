<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Rbac;

use NixPHP\CMS\Exceptions\PrivilegedActionDenied;
use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Handlers\Support\RejectsPrivilegedAction;
use NixPHP\CMS\Models\Permission;
use NixPHP\CMS\Repositories\PermissionRepository;
use NixPHP\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\CMS\user;

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

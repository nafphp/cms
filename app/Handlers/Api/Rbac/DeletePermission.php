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

final class DeletePermission
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $permissionId = (int) ($this->jsonBody()['id'] ?? 0);
        $repository = repo(PermissionRepository::class);
        /** @var Permission|null $old */
        $old = $repository->findOneBy('id', $permissionId);
        $deleted = $repository->deleteById($permissionId);
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

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

final class DeleteRole
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $roleId = (int) ($this->jsonBody()['id'] ?? 0);
        $repository = repo(RoleRepository::class);
        /** @var Role|null $old */
        $old = $repository->findOneBy('id', $roleId);
        $deleted = $repository->deleteById($roleId);
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete role.'], 400);
        }

        AuditEntry::create(
            'role.deleted',
            'role',
            (string) $roleId,
            $old instanceof Role ? ['id' => $old->getId(), 'name' => $old->getName()] : null,
        )->record();

        return json(['success' => true]);
    }
}

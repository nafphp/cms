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

final class DeleteRole
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $roleId = (int) ($this->jsonBody()['id'] ?? 0);
        $repository = repo(RoleRepository::class);
        /** @var Role|null $old */
        $old = $repository->findOneBy('id', $roleId);
        try {
            $deleted = $repository->deleteById((int) user()?->get()?->getId(), $roleId);
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'role.delete', 'role', (string) $roleId);
        }
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

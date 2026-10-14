<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Rbac;

use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Handlers\Support\RejectsPrivilegedAction;
use Naf\CMS\Models\Role;
use Naf\CMS\Repositories\RoleRepository;
use Naf\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\user;
use function Naf\json;
use function Naf\ORM\repo;

final class DeleteRole
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $roleId     = (int) ($this->jsonBody()['id'] ?? 0);
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

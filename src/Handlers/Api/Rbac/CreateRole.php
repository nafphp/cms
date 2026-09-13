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
use function Naf\json;
use function Naf\ORM\repo;
use function Naf\CMS\user;

final class CreateRole
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        try {
            $created = repo(RoleRepository::class)->createByName(
                (int) user()?->get()?->getId(),
                (string) ($this->jsonBody()['name'] ?? ''),
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'role.create', 'role', 'new');
        }
        if (!$created instanceof Role) {
            return json(['success' => false, 'reason' => 'Could not create role.'], 400);
        }

        AuditEntry::create(
            'role.created',
            'role',
            (string) $created->getId(),
            null,
            ['id' => $created->getId(), 'name' => $created->getName(), 'permission_names' => []],
        )->record();

        return json([
            'success' => true,
            'role' => [
                'id' => $created->getId(),
                'name' => $created->getName(),
                'permission_names' => [],
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace src\Handlers\Api\Rbac;

use src\Exceptions\PrivilegedActionDenied;
use src\Handlers\Support\ReadsJsonBody;
use src\Handlers\Support\RejectsPrivilegedAction;
use src\Models\Role;
use src\Repositories\RoleRepository;
use src\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixCMS\user;

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

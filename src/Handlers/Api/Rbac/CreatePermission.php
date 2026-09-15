<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Rbac;

use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Handlers\Support\RejectsPrivilegedAction;
use Naf\CMS\Models\Permission;
use Naf\CMS\Repositories\PermissionRepository;
use Naf\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\user;
use function Naf\json;
use function Naf\ORM\repo;

final class CreatePermission
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        try {
            $created = repo(PermissionRepository::class)->createByName(
                (int) user()?->get()?->getId(),
                (string) ($this->jsonBody()['name'] ?? ''),
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'permission.create', 'permission', 'new');
        }
        if (!$created instanceof Permission) {
            return json(['success' => false, 'reason' => 'Could not create permission.'], 400);
        }

        AuditEntry::create(
            'permission.created',
            'permission',
            (string) $created->getId(),
            null,
            ['id' => $created->getId(), 'name' => $created->getName(), 'role_count' => 0],
        )->record();

        return json([
            'success'    => true,
            'permission' => [
                'id'         => $created->getId(),
                'name'       => $created->getName(),
                'role_count' => 0,
            ],
        ]);
    }
}

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
            'success' => true,
            'permission' => [
                'id' => $created->getId(),
                'name' => $created->getName(),
                'role_count' => 0,
            ],
        ]);
    }
}

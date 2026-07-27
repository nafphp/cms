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

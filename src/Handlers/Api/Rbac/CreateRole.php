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

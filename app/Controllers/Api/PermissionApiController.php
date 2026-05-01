<?php

declare(strict_types=1);

namespace Cms\Controllers\Api;

use Cms\Controllers\AbstractController;
use Cms\Enums\Permission as PermissionEnum;
use Cms\Models\Permission;
use Cms\Repositories\PermissionRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\request;

final class PermissionApiController extends AbstractController
{
    public function list(): ResponseInterface
    {
        $this->requirePermission(PermissionEnum::PERMISSIONS_MANAGE);

        repo(PermissionRepository::class)->mergeDuplicateNames();

        return json(repo(PermissionRepository::class)->findAllWithRoleCount());
    }

    public function create(): ResponseInterface
    {
        $this->requirePermission(PermissionEnum::PERMISSIONS_MANAGE);

        $created = repo(PermissionRepository::class)->createByName((string) ($this->jsonBody()['name'] ?? ''));
        if (!$created instanceof Permission) {
            return json(['success' => false, 'reason' => 'Could not create permission.'], 400);
        }

        return json([
            'success' => true,
            'permission' => [
                'id' => $created->getId(),
                'name' => $created->getName(),
                'role_count' => 0,
            ],
        ]);
    }

    public function update(): ResponseInterface
    {
        $this->requirePermission(PermissionEnum::PERMISSIONS_MANAGE);

        $payload = $this->jsonBody();
        $updated = repo(PermissionRepository::class)->renameById(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['name'] ?? ''),
        );

        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update permission.'], 400);
        }

        return json(['success' => true]);
    }

    public function delete(): ResponseInterface
    {
        $this->requirePermission(PermissionEnum::PERMISSIONS_MANAGE);

        $deleted = repo(PermissionRepository::class)->deleteById((int) ($this->jsonBody()['id'] ?? 0));
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete permission.'], 400);
        }

        return json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $data = json_decode((string) request()->getBody(), true);

        return is_array($data) ? $data : [];
    }
}

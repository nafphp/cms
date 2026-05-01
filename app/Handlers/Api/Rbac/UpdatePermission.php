<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\PermissionRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class UpdatePermission
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
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
}

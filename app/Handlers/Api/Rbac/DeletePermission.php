<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\PermissionRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class DeletePermission
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $deleted = repo(PermissionRepository::class)->deleteById((int) ($this->jsonBody()['id'] ?? 0));
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete permission.'], 400);
        }

        return json(['success' => true]);
    }
}

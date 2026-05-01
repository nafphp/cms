<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Rbac;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\RoleRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class DeleteRole
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $deleted = repo(RoleRepository::class)->deleteById((int) ($this->jsonBody()['id'] ?? 0));
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete role.'], 400);
        }

        return json(['success' => true]);
    }
}

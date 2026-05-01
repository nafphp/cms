<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class UpdateUserRoles
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $userId = (int) ($data['userId'] ?? 0);
        $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
        if ($userId <= 0 || $roles === []) {
            return json(['success' => false, 'reason' => 'Please provide a user and at least one role.'], 400);
        }

        $repository = repo(UserRepository::class);
        if ($repository->updateRolesByUserId($userId, $roles) === []) {
            return json(['success' => false, 'reason' => 'Could not update roles for this user.'], 400);
        }

        $updatedUser = null;
        foreach ($repository->findAllWithRolesAndPermissions() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                $updatedUser = $user;
                break;
            }
        }

        return json(['success' => true, 'user' => $updatedUser]);
    }
}

<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\User;

use NixPHP\CMS\Exceptions\PrivilegedActionDenied;
use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Handlers\Support\RejectsPrivilegedAction;
use NixPHP\CMS\Repositories\UserRepository;
use NixPHP\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\CMS\user;

final class UpdateUserRoles
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $userId = (int) ($data['userId'] ?? 0);
        $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
        if ($userId <= 0 || $roles === []) {
            return json(['success' => false, 'reason' => 'Please provide a user and at least one role.'], 400);
        }

        $repository = repo(UserRepository::class);
        $oldUser = null;
        foreach ($repository->findAllWithRolesAndPermissions() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                $oldUser = $user;
                break;
            }
        }

        try {
            $updatedRoles = $repository->updateRolesByUserId((int) user()?->get()?->getId(), $userId, $roles);
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'user.roles.update', 'user', (string) $userId);
        }
        if ($updatedRoles === []) {
            return json(['success' => false, 'reason' => 'Could not update roles for this user.'], 400);
        }

        $updatedUser = null;
        foreach ($repository->findAllWithRolesAndPermissions() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                $updatedUser = $user;
                break;
            }
        }

        AuditEntry::create(
            'user.roles_updated',
            'user',
            (string) $userId,
            $oldUser,
            $updatedUser,
        )->record();

        return json(['success' => true, 'user' => $updatedUser]);
    }
}

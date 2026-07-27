<?php

declare(strict_types=1);

namespace src\Handlers\Api\User;

use src\Exceptions\PrivilegedActionDenied;
use src\Handlers\Support\ReadsJsonBody;
use src\Handlers\Support\RejectsPrivilegedAction;
use src\Repositories\UserRepository;
use src\Services\Security\PasswordPolicy;
use src\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixCMS\user;

final class UpdateUserPassword
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $userId = (int) ($data['user_id'] ?? 0);
        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');
        $errors = [];

        if ($userId <= 0) {
            $errors['user_id'][] = 'Invalid user id.';
        }

        if (!PasswordPolicy::accepts($password)) {
            $errors['password'][] = PasswordPolicy::message();
        }

        if ($password !== $confirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $repository = repo(UserRepository::class);
        if ($repository->findOneForForm($userId) === null) {
            return json(['success' => false, 'reason' => 'User not found.'], 404);
        }

        try {
            $updated = $repository->resetPasswordByActor((int) user()?->get()?->getId(), $userId, $password);
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'user.password.reset', 'user', (string) $userId);
        }
        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update password.'], 400);
        }

        AuditEntry::create(
            'user.password_updated',
            'user',
            (string) $userId,
            null,
            ['password_updated' => true],
        )->record();

        return json(['success' => true, 'userId' => $userId]);
    }
}

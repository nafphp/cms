<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;

final class UpdateUserPassword
{
    use ReadsJsonBody;

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

        if (mb_strlen(trim($password)) < 8) {
            $errors['password'][] = 'Please enter a password with at least 8 characters.';
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

        if (!$repository->upgradePasswordHash($userId, $password)) {
            return json(['success' => false, 'reason' => 'Could not update password.'], 400);
        }

        return json(['success' => true, 'userId' => $userId]);
    }
}

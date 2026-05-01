<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\route;

final class UpdateUser
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $userId = (int) ($data['user_id'] ?? 0);
        $errors = UserPayloadValidator::validate($data, false);
        if ($userId <= 0) {
            $errors['user_id'][] = 'Invalid user id.';
        }

        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $repository = repo(UserRepository::class);
        if ($repository->findOneForForm($userId) === null) {
            return json(['success' => false, 'reason' => 'User not found.'], 404);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($repository->usernameExistsForAnotherUser($username, $userId)) {
            return json(['success' => false, 'reason' => 'Username already exists.'], 409);
        }

        if ($email !== '' && $repository->emailExistsForAnotherUser($email, $userId)) {
            return json(['success' => false, 'reason' => 'Email already exists.'], 409);
        }

        $updated = $repository->updateWithRoles(
            $userId,
            $username,
            trim((string) ($data['password'] ?? '')) !== '' ? (string) $data['password'] : null,
            (string) ($data['firstname'] ?? ''),
            (string) ($data['lastname'] ?? ''),
            is_array($data['roles'] ?? null) ? $data['roles'] : [],
            $email !== '' ? $email : null,
        );

        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update user.'], 400);
        }

        return json([
            'success' => true,
            'userId' => $userId,
            'redirect' => route('admin.users'),
        ]);
    }
}

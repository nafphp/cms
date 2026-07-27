<?php

declare(strict_types=1);

namespace src\Handlers\Api\User;

use src\Handlers\Api\User\UserPayloadValidator;
use src\Handlers\Support\ReadsJsonBody;
use src\Repositories\UserRepository;
use src\Support\AuditEntry;
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
        $errors = UserPayloadValidator::validate($data, false, false);
        if ($userId <= 0) {
            $errors['user_id'][] = 'Invalid user id.';
        }

        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $repository = repo(UserRepository::class);
        $old = $repository->findOneForForm($userId);
        if ($old === null) {
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

        $updated = $repository->updateProfile(
            $userId,
            $username,
            (string) ($data['firstname'] ?? ''),
            (string) ($data['lastname'] ?? ''),
            $email !== '' ? $email : null,
        );

        if (!$updated) {
            return json(['success' => false, 'reason' => 'Could not update user.'], 400);
        }

        AuditEntry::create(
            'user.updated',
            'user',
            (string) $userId,
            $this->userAuditSnapshot($old),
            $this->userAuditSnapshot($repository->findOneForForm($userId)),
        )->record();

        return json([
            'success' => true,
            'userId' => $userId,
            'redirect' => route('admin.users'),
        ]);
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array<string, mixed>|null
     */
    private function userAuditSnapshot(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'email' => $user['email'] ?? null,
            'firstname' => (string) ($user['firstname'] ?? ''),
            'lastname' => (string) ($user['lastname'] ?? ''),
            'roles' => $user['roles'] ?? [],
        ];
    }
}

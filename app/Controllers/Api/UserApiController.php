<?php

declare(strict_types=1);

namespace Cms\Controllers\Api;

use Cms\Controllers\AbstractController;
use Cms\Enums\Permission;
use Cms\Models\User;
use Cms\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\request;
use function NixPHP\route;

final class UserApiController extends AbstractController
{
    public function list(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_VIEW);

        return json(repo(UserRepository::class)->findAllWithRolesAndPermissions());
    }

    public function store(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_CREATE);

        $data = $this->jsonBody();
        $errors = $this->validateUserPayload($data, true);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $repository = repo(UserRepository::class);
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($repository->usernameExistsForAnotherUser($username)) {
            return json(['success' => false, 'reason' => 'Username already exists.'], 409);
        }

        if ($email !== '' && $repository->emailExistsForAnotherUser($email)) {
            return json(['success' => false, 'reason' => 'Email already exists.'], 409);
        }

        $created = $repository->createWithRoles(
            $username,
            (string) ($data['password'] ?? ''),
            (string) ($data['firstname'] ?? ''),
            (string) ($data['lastname'] ?? ''),
            is_array($data['roles'] ?? null) ? $data['roles'] : [],
            $email !== '' ? $email : null,
        );

        if (!$created instanceof User) {
            return json(['success' => false, 'reason' => 'Could not create user.'], 400);
        }

        return json([
            'success' => true,
            'userId' => $created->getId(),
            'redirect' => route('admin.users'),
        ]);
    }

    public function update(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_EDIT);

        $data = $this->jsonBody();
        $userId = (int) ($data['user_id'] ?? 0);
        $errors = $this->validateUserPayload($data, false);
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

    public function updateRoles(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_EDIT);

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

    public function delete(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_DELETE);

        $data = $this->jsonBody();
        $userId = (int) ($data['userId'] ?? 0);
        if ($userId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid user id.'], 400);
        }

        if (\user()?->get()?->getId() === $userId) {
            return json(['success' => false, 'reason' => 'You cannot delete your own account.'], 400);
        }

        if (!repo(UserRepository::class)->deleteById($userId)) {
            return json(['success' => false, 'reason' => 'Could not delete user.'], 400);
        }

        return json(['success' => true]);
    }

    public function createInvite(): ResponseInterface
    {
        $this->requirePermission(Permission::USERS_CREATE);

        $data = $this->jsonBody();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
        $expiresInDays = (int) ($data['expiresInDays'] ?? 7);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json(['success' => false, 'reason' => 'Please provide a valid email address.'], 400);
        }

        if ($roles === []) {
            return json(['success' => false, 'reason' => 'Please select at least one role.'], 400);
        }

        if ($expiresInDays < 1 || $expiresInDays > 30) {
            return json(['success' => false, 'reason' => 'Expiry must be between 1 and 30 days.'], 400);
        }

        $normalizedRoles = array_map(static fn(mixed $role): string => strtolower(trim((string) $role)), $roles);
        if (in_array('admin', $normalizedRoles, true) && !$this->hasPermission(Permission::ROLES_MANAGE)) {
            return json(['success' => false, 'reason' => 'You cannot invite administrators.'], 403);
        }

        $repository = repo(UserRepository::class);
        if ($repository->emailExistsForAnotherUser($email)) {
            return json(['success' => false, 'reason' => 'Email already exists.'], 409);
        }

        $invite = $repository->createInvite(
            $email,
            $roles,
            \user()?->get()?->getId(),
            $expiresInDays,
            $this->hasPermission(Permission::ROLES_MANAGE),
        );

        if ($invite === null) {
            return json(['success' => false, 'reason' => 'Could not create invite.'], 400);
        }

        return json([
            'success' => true,
            'inviteLink' => route('auth.register', ['token' => $invite['token']]),
            'expiresAt' => $invite['expiresAt'],
            'email' => $invite['email'],
            'roles' => $invite['roles'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $data = json_decode((string) request()->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    private function validateUserPayload(array $data, bool $creating): array
    {
        $errors = [];
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');
        $roles = $data['roles'] ?? [];

        if (mb_strlen($username) < 3) {
            $errors['username'][] = 'Please enter a username with at least 3 characters.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        if ($creating && mb_strlen(trim($password)) < 8) {
            $errors['password'][] = 'Please enter a password with at least 8 characters.';
        }

        if (!$creating && trim($password) !== '' && mb_strlen(trim($password)) < 8) {
            $errors['password'][] = 'Please enter a password with at least 8 characters.';
        }

        if (trim($password) !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if (!is_array($roles) || $roles === []) {
            $errors['roles'][] = 'Please assign at least one role.';
        }

        return $errors;
    }
}

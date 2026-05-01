<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\User;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Models\User;
use NixCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\route;

final class StoreUser
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $errors = UserPayloadValidator::validate($data, true);
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
}

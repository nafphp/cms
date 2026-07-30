<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\User;

use NixPHP\CMS\Exceptions\PrivilegedActionDenied;
use NixPHP\CMS\Handlers\Api\User\UserPayloadValidator;
use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Handlers\Support\RejectsPrivilegedAction;
use NixPHP\CMS\Models\User;
use NixPHP\CMS\Repositories\UserRepository;
use NixPHP\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\route;
use function NixPHP\CMS\user;

final class StoreUser
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __construct(
        private readonly UserPayloadValidator $validator = new UserPayloadValidator(),
    ) {}

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $errors = $this->validator->validate($data, true);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $repository = repo(UserRepository::class);
        $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($repository->usernameExistsForAnotherUser($username)) {
            return json(['success' => false, 'reason' => 'Username already exists.'], 409);
        }

        if ($email !== '' && $repository->emailExistsForAnotherUser($email)) {
            return json(['success' => false, 'reason' => 'Email already exists.'], 409);
        }

        try {
            $created = $repository->createWithRolesByActor(
                (int) user()?->get()?->getId(),
                $username,
                (string) ($data['password'] ?? ''),
                (string) ($data['firstname'] ?? ''),
                (string) ($data['lastname'] ?? ''),
                $roles,
                $email !== '' ? $email : null,
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'user.create_with_roles', 'user', 'new');
        }

        if (!$created instanceof User) {
            return json(['success' => false, 'reason' => 'Could not create user.'], 400);
        }

        AuditEntry::create(
            'user.created',
            'user',
            (string) $created->getId(),
            null,
            [
                'id' => $created->getId(),
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'roles' => array_values($roles),
            ],
        )->record();

        return json([
            'success' => true,
            'userId' => $created->getId(),
            'redirect' => route('admin.users'),
        ]);
    }
}

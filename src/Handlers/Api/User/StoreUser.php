<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\User;

use Naf\CMS\Exceptions\PrivilegedActionDenied;
use Naf\CMS\Handlers\Api\User\UserPayloadValidator;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Handlers\Support\RejectsPrivilegedAction;
use Naf\CMS\Models\User;
use Naf\CMS\Repositories\UserRepository;
use Naf\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;
use function Naf\ORM\repo;
use function Naf\route;
use function Naf\CMS\user;

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

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
use function NixPHP\route;
use function NixPHP\CMS\user;

final class CreateInvite
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
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

        $repository = repo(UserRepository::class);
        if ($repository->emailExistsForAnotherUser($email)) {
            return json(['success' => false, 'reason' => 'Email already exists.'], 409);
        }

        try {
            $invite = $repository->createInvite(
                $email,
                $roles,
                (int) user()?->get()?->getId(),
                $expiresInDays,
            );
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'user.invite_with_roles', 'user_invite', $email);
        }

        if ($invite === null) {
            return json(['success' => false, 'reason' => 'Could not create invite.'], 400);
        }

        AuditEntry::create(
            'user.invite_created',
            'user_invite',
            (string) ($invite['email'] ?? $email),
            null,
            [
                'email' => $invite['email'] ?? $email,
                'roles' => $invite['roles'] ?? $roles,
                'expires_at' => $invite['expiresAt'] ?? null,
            ],
        )->record();

        return json([
            'success' => true,
            'inviteLink' => route('auth.register', ['token' => $invite['token']]),
            'expiresAt' => $invite['expiresAt'],
            'email' => $invite['email'],
            'roles' => $invite['roles'],
        ]);
    }
}

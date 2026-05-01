<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Auth;

use NixCMS\Models\User;
use NixCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\Form\validator;
use function NixPHP\ORM\repo;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\View\render;

final class RegisterInvitedUser
{
    public function __invoke(string $token): ResponseInterface
    {
        $repository = repo(UserRepository::class);
        $invite = $repository->findInviteByToken($token);
        if ($invite === null) {
            return $this->registerView($token, null, 'Invite link is invalid.');
        }

        $stateError = $this->inviteStateError($invite);
        if ($stateError !== null) {
            return $this->registerView($token, $invite, $stateError);
        }

        $data = request()->getParsedBody() ?? [];
        $validation = validator()->validate($data, [
            'email' => 'required',
            'username' => 'required|min:3',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password',
            '_csrf' => 'required',
        ]);

        if (!$validation->isValid()) {
            return $this->registerView($token, $invite, implode(' ', validator()->getErrorMessages()));
        }

        $result = $repository->registerUserWithInvite(
            $token,
            (string) ($data['email'] ?? ''),
            (string) ($data['username'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['firstname'] ?? ''),
            (string) ($data['lastname'] ?? ''),
        );

        if (($result['user'] ?? null) instanceof User) {
            return redirect('/admin/login');
        }

        return $this->registerView(
            $token,
            $invite,
            $this->registrationError((string) ($result['reason'] ?? 'create_failed')),
        );
    }

    /**
     * @param array{id:int,email:string,roles:array<int,string>,expires_at:string,used_at:?string} $invite
     */
    private function inviteStateError(array $invite): ?string
    {
        if ($invite['used_at'] !== null) {
            return 'This invite link has already been used.';
        }

        $expiresAt = strtotime((string) ($invite['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            return 'This invite link has expired.';
        }

        return null;
    }

    private function registrationError(string $reason): string
    {
        return match ($reason) {
            'invalid_invite' => 'Invite link is invalid.',
            'invite_used' => 'This invite link has already been used.',
            'invite_expired' => 'This invite link has expired.',
            'invite_email_mismatch' => 'The email does not match this invite.',
            'username_exists' => 'Username already exists.',
            'email_exists' => 'Email already exists.',
            'invalid_roles' => 'This invite is not configured with valid roles.',
            default => 'Could not complete registration. Please try again.',
        };
    }

    /**
     * @param array{id:int,email:string,roles:array<int,string>,expires_at:string,used_at:?string}|null $invite
     */
    private function registerView(string $token, ?array $invite, string $error): ResponseInterface
    {
        return render('auth.register', [
            'title' => 'Register',
            'token' => $token,
            'invite' => $invite,
            'error' => $error,
        ]);
    }
}

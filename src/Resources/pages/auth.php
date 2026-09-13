<?php

declare(strict_types=1);

use Naf\CMS\Handlers\Page\Auth\LoginUser;
use Naf\CMS\Handlers\Page\Auth\RegisterInvitedUser;
use Naf\CMS\Page\Page;
use Naf\CMS\Repositories\UserRepository;
use function Naf\ORM\repo;
use function Naf\redirect;
use function Naf\Session\session;
use function Naf\View\render;
use function Naf\CMS\user;

$inviteStateError = static function (array $invite): ?string {
    if ($invite['used_at'] !== null) {
        return 'This invite link has already been used.';
    }

    $expiresAt = strtotime((string) ($invite['expires_at'] ?? ''));
    if ($expiresAt === false || $expiresAt < time()) {
        return 'This invite link has expired.';
    }

    return null;
};

return [
    Page::create('auth.login')
        ->setPath('/admin/login')
        ->setTitle('Login')
        ->setDataHandler(LoginUser::class)
        ->setContent(static function () {
            if (user()?->isLoggedIn() === true) {
                return redirect('/admin');
            }

            return render('auth.login', [
                'title' => 'Login',
                'redirectUri' => (string) (session()->getFlash('redirectUri') ?? ''),
                'error' => null,
                'username' => '',
            ]);
        }),

    Page::create('auth.logout')
        ->setPath('/admin/logout')
        ->setMethod('POST')
        ->setTitle('Logout')
        ->requireAuthentication()
        ->setContent(static function () {
            \Naf\Auth\auth()->logout();
            session()->clear();
            return redirect('/admin/login');
        }),

    Page::create('auth.register')
        ->setPath('/register/{token}')
        ->setTitle('Register')
        ->setDataHandler(RegisterInvitedUser::class)
        ->setContent(static function (string $token) use ($inviteStateError) {
            /** @var array{id:int,email:string,roles:array<int,string>,expires_at:string,used_at:?string}|null $invite */
            $invite = repo(UserRepository::class)->findInviteByToken($token);

            return render('auth.register', [
                'title' => 'Register',
                'token' => $token,
                'invite' => $invite,
                'error' => $invite !== null ? $inviteStateError($invite) : 'Invite link is invalid.',
            ]);
        }),
];

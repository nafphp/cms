<?php

declare(strict_types=1);

use NixCMS\Handlers\Page\Auth\LoginUser;
use NixCMS\Handlers\Page\Auth\RegisterInvitedUser;
use NixCMS\Page\Page;
use NixCMS\Repositories\UserRepository;
use function NixPHP\ORM\repo;
use function NixPHP\redirect;
use function NixPHP\Session\session;
use function NixPHP\View\render;

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
            if (\user()?->isLoggedIn() === true) {
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
        ->setTitle('Logout')
        ->setContent(static function () {
            session()->forget('userId');
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

<?php

declare(strict_types=1);

namespace NixCMS\Events;

use NixCMS\Exceptions\AuthException;
use NixCMS\Models\User;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\route;
use function NixPHP\Session\session;
use function NixPHP\View\render;
use function NixCMS\user;

class AuthRedirectEvent
{
    public function onException(\Throwable $t): ?ResponseInterface
    {
        if (!$t instanceof AuthException) {
            return null;
        }

        if (session()->get('userId') !== null && user()?->get() instanceof User) {
            $response = render('errors.403');
            return $response->withStatus(403);
        }

        session()->flash('redirectUri', $this->redirectUri());

        return redirect(route('auth.login'));
    }

    private function redirectUri(): string
    {
        try {
            return (string) request()->getUri();
        } catch (\Throwable) {
            return '/admin';
        }
    }
}

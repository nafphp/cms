<?php

declare(strict_types=1);

namespace Cms\Events;

use Cms\Exceptions\AuthException;
use Cms\Models\User;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\route;
use function NixPHP\Session\session;
use function NixPHP\View\render;

class AuthRedirectEvent
{
    public function onException(\Throwable $t): ?ResponseInterface
    {
        if (!$t instanceof AuthException) {
            return null;
        }

        if (session()->get('userId') !== null && \user()?->get() instanceof User) {
            $response = render('errors.403');
            return $response->withStatus(403);
        }

        session()->flash('redirectUri', (string) request()->getUri());

        return redirect(route('auth.login'));
    }
}

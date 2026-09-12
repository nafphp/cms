<?php

declare(strict_types=1);

namespace NixPHP\CMS\Events;

use NixPHP\CMS\Exceptions\AuthException;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\route;
use function NixPHP\Session\session;
use function NixPHP\View\render;
use function NixPHP\CMS\user;

class AuthRedirectEvent
{
    public function onException(\Throwable $t): ?ResponseInterface
    {
        if (!$t instanceof AuthException) {
            return null;
        }

        $status = in_array($t->getCode(), [401, 403], true)
            ? $t->getCode()
            : (user()?->isLoggedIn() === true ? 403 : 401);
        $path = (string) (parse_url($this->redirectUri(), PHP_URL_PATH) ?? '');
        if (str_starts_with($path, '/api/')) {
            return \NixPHP\json(['success' => false, 'reason' => $status === 401 ? 'Authentication required.' : 'Permission denied.'], $status);
        }
        if ($status === 403) {
            return render('errors.403')->withStatus(403);
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

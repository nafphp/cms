<?php

declare(strict_types=1);

namespace Cms\Events;

use Cms\Enums\Permission;
use Cms\Exceptions\AuthException;
use Psr\Http\Message\ServerRequestInterface;

class AdminGuardEvent
{
    private const array PUBLIC_ADMIN_PATHS = [
        '/admin/login',
        '/admin/logout',
    ];

    public function handle(ServerRequestInterface $request): void
    {
        $path = $request->getUri()->getPath();

        if (!str_starts_with($path, '/admin')) {
            return;
        }

        if (in_array($path, self::PUBLIC_ADMIN_PATHS, true)) {
            return;
        }

        $helper = \user();
        if ($helper === null || !$helper->isLoggedIn()) {
            throw new AuthException();
        }

        if (!$helper->can(Permission::ADMIN_ACCESS)) {
            throw new AuthException();
        }
    }
}

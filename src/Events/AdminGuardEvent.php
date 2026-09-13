<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

use Naf\CMS\Enums\Permission;
use Naf\CMS\Exceptions\AuthException;
use Psr\Http\Message\ServerRequestInterface;
use function Naf\CMS\user;

class AdminGuardEvent
{
    private const array PUBLIC_ADMIN_PATHS = [
        '/admin/login',
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

        $helper = user();
        try {
            (new \Naf\CMS\Services\Security\ResourceAuthorization())->authorize(
                $helper?->isLoggedIn() === true ? $helper : null,
                permissions: [Permission::ADMIN_ACCESS],
            );
        } catch (\Naf\Auth\Exceptions\UnauthenticatedException|\Naf\Auth\Exceptions\ForbiddenException $exception) {
            throw new AuthException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}

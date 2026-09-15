<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\Auth\Exceptions\ForbiddenException;
use Naf\Auth\Exceptions\UnauthenticatedException;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Exceptions\AuthException;
use Naf\CMS\Services\Security\ResourceAuthorization;

use function Naf\CMS\user;

final class PageAuthorization
{
    public function authorize(Page $page, ?Permission $extraPermission = null): void
    {
        $helper = user();
        $actor  = $helper?->isLoggedIn() === true ? $helper : null;
        $access = new ResourceAuthorization();

        try {
            $access->authorize(
                $actor,
                $page->requiresAuthentication(),
                $page->requiredRoles(),
                $page->requiredPermissions(),
                $page->requiresAnyPermission(),
            );
            if ($extraPermission !== null) {
                $access->authorize($actor, permissions: [$extraPermission]);
            }
        } catch (UnauthenticatedException|ForbiddenException $exception) {
            throw new AuthException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}

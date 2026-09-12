<?php

declare(strict_types=1);

namespace NixPHP\CMS\Page;

use NixPHP\CMS\Services\Security\ResourceAuthorization;
use NixPHP\Auth\Exceptions\{ForbiddenException, UnauthenticatedException};
use NixPHP\CMS\Exceptions\AuthException;
use NixPHP\CMS\Enums\Permission;
use function NixPHP\CMS\user;

final class PageAuthorization
{
    public function authorize(Page $page, ?Permission $extraPermission = null): void
    {
        $helper = user();
        $actor = $helper?->isLoggedIn() === true ? $helper : null;
        $access = new ResourceAuthorization();
        try {
            $access->authorize($actor, $page->requiresAuthentication(), $page->requiredRoles(),
                $page->requiredPermissions(), $page->requiresAnyPermission());
            if ($extraPermission !== null) {
                $access->authorize($actor, permissions: [$extraPermission]);
            }
        } catch (UnauthenticatedException|ForbiddenException $exception) {
            throw new AuthException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}

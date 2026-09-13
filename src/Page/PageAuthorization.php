<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Services\Security\ResourceAuthorization;
use Naf\Auth\Exceptions\{ForbiddenException, UnauthenticatedException};
use Naf\CMS\Exceptions\AuthException;
use Naf\CMS\Enums\Permission;
use function Naf\CMS\user;

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

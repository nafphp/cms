<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Security;

use NixPHP\Auth\Exceptions\{ForbiddenException, UnauthenticatedException};
use NixPHP\CMS\Enums\{Permission, Role};
use NixPHP\CMS\Support\UserHelper;

/** Apply page/API requirements with the same rules in guards and navigation. */
final class ResourceAuthorization
{
    /**
     * @param list<Role> $roles
     * @param list<Permission> $permissions
     */
    public function authorize(
        ?UserHelper $actor,
        bool $requiresAuthentication = false,
        array $roles = [],
        array $permissions = [],
        bool $anyPermission = false,
    ): void {
        if (!$requiresAuthentication && $roles === [] && $permissions === []) {
            return;
        }
        if ($actor === null || !$actor->isLoggedIn()) {
            throw new UnauthenticatedException();
        }
        if (!$actor->hasRole(...$roles)) {
            throw new ForbiddenException();
        }
        if ($permissions !== [] && !($anyPermission
            ? $actor->canAny(...$permissions)
            : $actor->can(...$permissions))) {
            throw new ForbiddenException();
        }
    }
}

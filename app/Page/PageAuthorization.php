<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixCMS\Enums\Permission;
use NixCMS\Exceptions\AuthException;

final class PageAuthorization
{
    public function authorize(Page $page, ?Permission $extraPermission = null): void
    {
        $helper = \user();

        if (($page->requiresAuthentication() || $extraPermission !== null) && ($helper === null || !$helper->isLoggedIn())) {
            throw new AuthException();
        }

        foreach ($page->requiredRoles() as $role) {
            if ($helper?->hasRole($role) !== true) {
                throw new AuthException();
            }
        }

        $permissions = $page->requiredPermissions();
        if ($permissions !== [] && $page->requiresAnyPermission()) {
            $matched = false;
            foreach ($permissions as $permission) {
                if ($helper?->can($permission) === true) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                throw new AuthException();
            }
        }

        if ($permissions !== [] && !$page->requiresAnyPermission()) {
            foreach ($permissions as $permission) {
                if ($helper?->can($permission) !== true) {
                    throw new AuthException();
                }
            }
        }

        if ($extraPermission !== null && $helper?->can($extraPermission) !== true) {
            throw new AuthException();
        }
    }
}

<?php

declare(strict_types=1);

namespace src\Api;

use src\Exceptions\AuthException;
use src\Api\ApiEndpoint;
use function NixCMS\user;

final class ApiAuthorization
{
    public function authorize(ApiEndpoint $endpoint): void
    {
        $helper = user();

        if ($endpoint->requiresAuthentication() && ($helper === null || !$helper->isLoggedIn())) {
            throw new AuthException();
        }

        foreach ($endpoint->requiredRoles() as $role) {
            if ($helper?->hasRole($role) !== true) {
                throw new AuthException();
            }
        }

        $permissions = $endpoint->requiredPermissions();
        if ($permissions === []) {
            return;
        }

        if ($endpoint->requiresAnyPermission()) {
            foreach ($permissions as $permission) {
                if ($helper?->can($permission) === true) {
                    return;
                }
            }

            throw new AuthException();
        }

        foreach ($permissions as $permission) {
            if ($helper?->can($permission) !== true) {
                throw new AuthException();
            }
        }
    }
}

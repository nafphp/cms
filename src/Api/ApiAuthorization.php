<?php

declare(strict_types=1);

namespace NixPHP\CMS\Api;

use NixPHP\CMS\Services\Security\ResourceAuthorization;
use NixPHP\Auth\Exceptions\{ForbiddenException, UnauthenticatedException};
use NixPHP\CMS\Exceptions\AuthException;
use function NixPHP\CMS\user;

final class ApiAuthorization
{
    public function authorize(ApiEndpoint $endpoint): void
    {
        $helper = user();
        $actor = $helper?->isLoggedIn() === true ? $helper : null;
        $access = new ResourceAuthorization();
        try {
            $access->authorize($actor, $endpoint->requiresAuthentication(), $endpoint->requiredRoles(),
                $endpoint->requiredPermissions(), $endpoint->requiresAnyPermission());
        } catch (UnauthenticatedException|ForbiddenException $exception) {
            throw new AuthException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}

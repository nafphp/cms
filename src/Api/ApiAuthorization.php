<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use Naf\CMS\Services\Security\ResourceAuthorization;
use Naf\Auth\Exceptions\{ForbiddenException, UnauthenticatedException};
use Naf\CMS\Exceptions\AuthException;
use function Naf\CMS\user;

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

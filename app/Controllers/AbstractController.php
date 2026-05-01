<?php

declare(strict_types=1);

namespace Cms\Controllers;

use Cms\Enums\Permission;
use Cms\Enums\Role;
use Cms\Exceptions\AuthException;
use Cms\Models\User;

class AbstractController
{
    protected function isLoggedIn(): bool
    {
        return \user()?->isLoggedIn() === true;
    }

    protected function getUser(): ?User
    {
        return \user()?->get();
    }

    protected function isAuthenticated(Role $role): bool
    {
        return \user()?->hasRole($role) === true;
    }

    protected function hasPermission(Permission $permission): bool
    {
        return \user()?->can($permission) === true;
    }

    protected function requirePermission(Permission $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new AuthException();
        }
    }

    protected function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            throw new AuthException();
        }
    }
}

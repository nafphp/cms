<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Enums\Permission;
use NixCMS\Enums\Role;
use NixCMS\Models\User;

class UserHelper
{
    /**
     * @param array<int, Permission>|null $permissions
     * @param array<int, Role>|null $roles
     */
    public function __construct(
        private ?User $user,
        private ?array $permissions = null,
        private ?array $roles = null,
        private bool $loggedIn = false,
    ) {
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions ?? $this->user?->getPermissions() ?? [], true);
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles ?? $this->user?->getRoles() ?? [], true);
    }

    public function isLoggedIn(): bool
    {
        return $this->loggedIn || $this->user instanceof User;
    }

    public function get(): ?User
    {
        return $this->user;
    }
}

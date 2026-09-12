<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support;

use NixPHP\Auth\Auth;
use NixPHP\CMS\Enums\{Permission, Role};
use NixPHP\CMS\Models\User;
use NixPHP\CMS\Services\Security\UserIdentity;

/** Compatibility facade for CMS views and handlers; auth evaluates grants. */
class UserHelper
{
    private readonly Auth $authorization;

    /**
     * @param array<int, Permission>|null $permissions
     * @param array<int, Role>|null $roles
     */
    public function __construct(
        private ?User $user,
        ?array $permissions = null,
        ?array $roles = null,
        private bool $loggedIn = false,
    ) {
        // This evaluator has no session store and cannot change the login.
        $this->authorization = new Auth();
        if ($this->isLoggedIn()) {
            $this->authorization->setIdentity(new UserIdentity($user, $permissions, $roles));
        }
    }

    public function can(Permission ...$permissions): bool
    {
        return $this->authorization->can(...$permissions);
    }

    public function canAny(Permission ...$permissions): bool
    {
        return $this->authorization->canAny(...$permissions);
    }

    public function hasRole(Role ...$roles): bool
    {
        return $this->authorization->hasRole(...$roles);
    }

    public function isLoggedIn(): bool { return $this->loggedIn || $this->user instanceof User; }
    public function get(): ?User { return $this->user; }
}

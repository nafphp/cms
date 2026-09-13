<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use Naf\Auth\Identity\IdentityInterface;
use Naf\CMS\Models\User;

/** CMS model adapter; permission persistence and enums remain in the CMS. */
final readonly class UserIdentity implements IdentityInterface
{
    /**
     * @param list<string|\BackedEnum>|null $permissions
     * @param list<string|\BackedEnum>|null $roles
     */
    public function __construct(
        public ?User $user,
        private ?array $permissions = null,
        private ?array $roles = null,
    ) {}

    public function getIdentifier(): string { return (string) ($this->user?->getId() ?? 'cms-test-override'); }
    public function getPermissions(): iterable { return $this->permissions ?? $this->user?->getPermissions() ?? []; }
    public function getRoles(): iterable { return $this->roles ?? $this->user?->getRoles() ?? []; }
}

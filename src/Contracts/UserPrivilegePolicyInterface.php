<?php

declare(strict_types=1);

namespace NixPHP\CMS\Contracts;

use NixPHP\CMS\Enums\Permission;

/**
 * Authorization boundary for user, role, credential, and deletion mutations.
 *
 * Every method either returns silently or throws PrivilegedActionDenied. They are
 * assertions, not predicates: a host replacing this interface must throw to deny,
 * because callers treat a normal return as "allowed".
 */
interface UserPrivilegePolicyInterface
{
    /** @param array<int, mixed> $roleNames */
    public function assertRoleAssignment(
        int $actorId,
        ?int $targetId,
        array $roleNames,
        Permission $userPermission = Permission::USERS_EDIT,
    ): void;

    public function assertPasswordReset(int $actorId, int $targetId): void;

    public function assertDeletion(int $actorId, int $targetId): void;

    public function assertRoleDefinitionCreate(int $actorId): void;

    public function assertRoleDefinitionChange(int $actorId, int $roleId): void;

    /** @param array<int, mixed> $permissionIds */
    public function assertRolePermissionChange(int $actorId, int $roleId, array $permissionIds): void;

    public function assertPermissionDefinitionCreate(int $actorId): void;

    public function assertPermissionDefinitionChange(int $actorId, int $permissionId): void;
}

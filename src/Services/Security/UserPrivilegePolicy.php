<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use Naf\CMS\Contracts\UserPrivilegePolicyInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Enums\Role;
use Naf\CMS\Exceptions\PrivilegedActionDenied;
use PDO;
use RuntimeException;

use function Naf\Database\database;

/**
 * Central authorization boundary for user, role, credential, and deletion mutations.
 */
final class UserPrivilegePolicy implements UserPrivilegePolicyInterface
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /** @param array<int, mixed> $roleNames */
    public function assertRoleAssignment(
        int $actorId,
        ?int $targetId,
        array $roleNames,
        Permission $userPermission = Permission::USERS_EDIT,
    ): void {
        $actor = $this->profile($actorId);
        $this->requirePermission($actor, $userPermission);
        $this->requirePermission($actor, Permission::ROLES_MANAGE);

        $roles = $this->roleProfiles($roleNames);
        if ($roles === []) {
            $this->deny('invalid_roles', 'At least one existing role is required.');
        }

        $requestedPermissions = [];
        foreach ($roles as $role) {
            foreach ($role['permissions'] as $permission) {
                $requestedPermissions[$permission] = true;
            }
        }

        $outsideScope = array_diff(array_keys($requestedPermissions), $actor['permissions']);
        if ($outsideScope !== []) {
            $this->deny('role_outside_actor_scope', 'You cannot assign permissions you do not have.');
        }

        $assignsAdmin = isset($roles[Role::ADMIN->value]);
        if ($assignsAdmin && !$this->hasRole($actor, Role::ADMIN)) {
            $this->deny('admin_assignment_requires_admin', 'Only an administrator can assign the admin role.');
        }

        if ($targetId === null) {
            return;
        }

        $target = $this->profile($targetId);
        if ($this->hasRole($target, Role::ADMIN) && !$assignsAdmin && $this->adminCount() <= 1) {
            $this->deny('last_admin', 'The last administrator cannot lose the admin role.');
        }

        if ($targetId === $actorId) {
            $this->deny('self_role_change', 'You cannot change your own roles.');
        }

        $this->assertTargetWithinScope($actor, $target);
    }

    public function assertPasswordReset(int $actorId, int $targetId): void
    {
        $actor = $this->profile($actorId);
        $this->requirePermission($actor, Permission::USERS_EDIT);
        $target = $this->profile($targetId);

        if ($actorId !== $targetId) {
            $this->assertTargetWithinScope($actor, $target);
        }
    }

    public function assertDeletion(int $actorId, int $targetId): void
    {
        $actor = $this->profile($actorId);
        $this->requirePermission($actor, Permission::USERS_DELETE);
        $target = $this->profile($targetId);

        if ($this->hasRole($target, Role::ADMIN) && $this->adminCount() <= 1) {
            $this->deny('last_admin', 'The last administrator cannot be deleted.');
        }

        if ($actorId === $targetId) {
            $this->deny('self_delete', 'You cannot delete your own account.');
        }

        $this->assertTargetWithinScope($actor, $target);
    }

    /** @param array<int, mixed> $permissionIds */
    public function assertRolePermissionChange(int $actorId, int $roleId, array $permissionIds): void
    {
        $actor = $this->profile($actorId);
        $this->requirePermission($actor, Permission::ROLES_MANAGE);
        $this->requirePermission($actor, Permission::PERMISSIONS_MANAGE);

        $role = $this->roleProfileById($roleId);
        if ($role === null) {
            $this->deny('role_not_found', 'Role not found.');
        }

        if ($this->actorHasRoleId($actorId, $roleId)) {
            $this->deny('self_role_permission_change', 'You cannot change permissions of one of your own roles.');
        }

        if ($role['name'] === Role::ADMIN->value && !$this->hasRole($actor, Role::ADMIN)) {
            $this->deny('admin_role_requires_admin', 'Only an administrator can change the admin role.');
        }

        $requested = $this->permissionNamesByIds($permissionIds);
        if (count($requested) !== count($this->normalizeIds($permissionIds))) {
            $this->deny('invalid_permissions', 'One or more permissions do not exist.');
        }

        if (array_diff($requested, $actor['permissions']) !== []) {
            $this->deny('permission_outside_actor_scope', 'You cannot grant permissions you do not have.');
        }
    }

    public function assertRoleDefinitionChange(int $actorId, int $roleId): void
    {
        $actor = $this->profile($actorId);
        $this->requirePermission($actor, Permission::ROLES_MANAGE);

        $role = $this->roleProfileById($roleId);
        if ($role === null) {
            $this->deny('role_not_found', 'Role not found.');
        }

        if ($role['name'] === Role::ADMIN->value && $this->adminCount() <= 1) {
            $this->deny('last_admin', 'The role of the last administrator cannot be changed or deleted.');
        }

        if ($this->actorHasRoleId($actorId, $roleId)) {
            $this->deny('self_role_definition_change', 'You cannot change or delete one of your own roles.');
        }

        if ($role['name'] === Role::ADMIN->value && !$this->hasRole($actor, Role::ADMIN)) {
            $this->deny('admin_role_requires_admin', 'Only an administrator can change the admin role.');
        }

        $rolePermissions = $this->column(
            'SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :id',
            ['id' => $roleId],
        );
        if (array_diff($rolePermissions, $actor['permissions']) !== []) {
            $this->deny('role_outside_actor_scope', 'You cannot change a role outside your permission scope.');
        }
    }

    public function assertRoleDefinitionCreate(int $actorId): void
    {
        $this->requirePermission($this->profile($actorId), Permission::ROLES_MANAGE);
    }

    public function assertPermissionDefinitionCreate(int $actorId): void
    {
        $this->requirePermission($this->profile($actorId), Permission::PERMISSIONS_MANAGE);
    }

    public function assertPermissionDefinitionChange(int $actorId, int $permissionId): void
    {
        $actor = $this->profile($actorId);
        $this->requirePermission($actor, Permission::PERMISSIONS_MANAGE);

        $statement = $this->connection()->prepare('SELECT name FROM permissions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $permissionId]);
        $name = $statement->fetchColumn();
        if (!is_string($name) || $name === '') {
            $this->deny('permission_not_found', 'Permission not found.');
        }

        if ($this->actorHasPermissionId($actorId, $permissionId)) {
            $this->deny(
                'self_permission_definition_change',
                'You cannot rename or delete a permission granted to one of your own roles.',
            );
        }

        $assigned = (int) $this->scalar(
            'SELECT COUNT(*) FROM role_permissions WHERE permission_id = :id',
            ['id' => $permissionId],
        ) > 0;
        if ($assigned && !in_array($name, $actor['permissions'], true)) {
            $this->deny('permission_outside_actor_scope', 'You cannot change a permission outside your scope.');
        }
    }

    /** @param array{roles:array<int,string>,permissions:array<int,string>} $actor */
    private function requirePermission(array $actor, Permission $permission): void
    {
        if (!in_array($permission->value, $actor['permissions'], true)) {
            $this->deny('missing_' . str_replace('.', '_', $permission->value), 'A required permission is missing.');
        }
    }

    /**
     * @param array{roles:array<int,string>,permissions:array<int,string>} $actor
     * @param array{roles:array<int,string>,permissions:array<int,string>} $target
     */
    private function assertTargetWithinScope(array $actor, array $target): void
    {
        if ($this->hasRole($target, Role::ADMIN) && !$this->hasRole($actor, Role::ADMIN)) {
            $this->deny('higher_privileged_target', 'You cannot manage a higher privileged account.');
        }

        if (array_diff($target['permissions'], $actor['permissions']) !== []) {
            $this->deny('higher_privileged_target', 'You cannot manage a higher privileged account.');
        }
    }

    /** @return array{roles:array<int,string>,permissions:array<int,string>} */
    private function profile(int $userId): array
    {
        if ($userId <= 0 || !$this->userExists($userId)) {
            $this->deny('user_not_found', 'User not found.');
        }

        $roles = $this->column(
            'SELECT r.name FROM user_role ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id ORDER BY r.name',
            ['id' => $userId],
        );
        $permissions = $this->column(
            'SELECT DISTINCT p.name FROM user_role ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = :id ORDER BY p.name',
            ['id' => $userId],
        );

        return ['roles' => $roles, 'permissions' => $permissions];
    }

    /**
     * @param array<int, mixed> $roleNames
     * @return array<string, array{name:string,permissions:array<int,string>}>
     */
    private function roleProfiles(array $roleNames): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn(mixed $role): string => strtolower(trim((string) $role)),
            $roleNames,
        ))));
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $statement    = $this->connection()->prepare("SELECT id, name FROM roles WHERE name IN ($placeholders)");
        $statement->execute($names);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== count($names)) {
            $this->deny('invalid_roles', 'One or more roles do not exist.');
        }

        $profiles = [];
        foreach ($rows as $row) {
            $roleId          = (int) ($row['id'] ?? 0);
            $name            = (string) ($row['name'] ?? '');
            $profiles[$name] = [
                'name'        => $name,
                'permissions' => $this->column(
                    'SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :id ORDER BY p.name',
                    ['id' => $roleId],
                ),
            ];
        }

        return $profiles;
    }

    /** @return array{id:int,name:string}|null */
    private function roleProfileById(int $roleId): ?array
    {
        $statement = $this->connection()->prepare('SELECT id, name FROM roles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $roleId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ['id' => (int) $row['id'], 'name' => (string) $row['name']] : null;
    }

    /**
     * @param array<int, mixed> $permissionIds
     * @return list<string>
     */
    private function permissionNamesByIds(array $permissionIds): array
    {
        $ids = $this->normalizeIds($permissionIds);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement    = $this->connection()->prepare("SELECT name FROM permissions WHERE id IN ($placeholders) ORDER BY name");
        $statement->execute($ids);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<int, mixed> $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn(mixed $id): int => (int) $id, $ids),
            static fn(int $id): bool => $id > 0,
        )));
    }

    private function actorHasRoleId(int $actorId, int $roleId): bool
    {
        $statement = $this->connection()->prepare('SELECT 1 FROM user_role WHERE user_id = :userId AND role_id = :roleId LIMIT 1');
        $statement->execute(['userId' => $actorId, 'roleId' => $roleId]);

        return (bool) $statement->fetchColumn();
    }

    private function actorHasPermissionId(int $actorId, int $permissionId): bool
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM user_role ur JOIN role_permissions rp ON rp.role_id = ur.role_id WHERE ur.user_id = :user_id AND rp.permission_id = :permission_id',
            ['user_id' => $actorId, 'permission_id' => $permissionId],
        ) > 0;
    }

    private function userExists(int $userId): bool
    {
        $statement = $this->connection()->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);

        return (bool) $statement->fetchColumn();
    }

    private function adminCount(): int
    {
        $statement = $this->connection()->prepare('SELECT COUNT(DISTINCT ur.user_id) FROM user_role ur JOIN roles r ON r.id = ur.role_id WHERE r.name = :role');
        $statement->execute(['role' => Role::ADMIN->value]);

        return (int) $statement->fetchColumn();
    }

    /** @param array{roles:array<int,string>,permissions:array<int,string>} $profile */
    private function hasRole(array $profile, Role $role): bool
    {
        return in_array($role->value, $profile['roles'], true);
    }

    /**
     * @param array<string, int|string> $parameters
     * @return array<int, string>
     */
    private function column(string $sql, array $parameters): array
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param array<string, int|string> $parameters */
    private function scalar(string $sql, array $parameters): mixed
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    private function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $connection = database();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('No database connection available.');
        }

        return $this->connection = $connection;
    }

    private function deny(string $reason, string $message): never
    {
        throw new PrivilegedActionDenied($reason, $message);
    }
}

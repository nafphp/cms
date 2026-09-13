<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use Naf\CMS\Contracts\UserPrivilegePolicyInterface;
use Naf\CMS\Models\Role;
use Naf\CMS\Services\Security\UserPrivilegePolicy;
use Naf\ORM\Core\EntityManager;
use Naf\ORM\Repository\AbstractRepository;
use PDO;
use function Naf\log;

class RoleRepository extends AbstractRepository
{
    private const string ROLE_PERMISSION_TABLE = 'role_permissions';

    public function __construct(
        protected PDO $pdo,
        protected EntityManager $entityManager,
        private ?UserPrivilegePolicyInterface $privilegePolicy = null,
    ) {
        parent::__construct($pdo, $entityManager);
    }

    /**
     * The container binding wins when the repository is autowired. The fallback
     * keeps this repository's own connection, which matters for tests that build
     * it with a dedicated PDO instead of the application database.
     */
    private function privileges(): UserPrivilegePolicyInterface
    {
        return $this->privilegePolicy ??= new UserPrivilegePolicy($this->pdo);
    }

    protected function getEntityClass(): string
    {
        return Role::class;
    }

    /**
     * @return array<int, array{id:int,name:string,permission_names:array<int,string>}>
     */
    public function findAllWithPermissions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                r.id,
                r.name,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR \',\') AS permission_names
            FROM roles r
            LEFT JOIN ' . self::ROLE_PERMISSION_TABLE . ' rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            GROUP BY r.id, r.name
            ORDER BY r.name ASC'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static function (array $row): array {
                $permissions = array_values(array_filter(array_map(
                    static fn(string $permission): string => trim($permission),
                    explode(',', (string) ($row['permission_names'] ?? ''))
                )));

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'permission_names' => $permissions,
                ];
            },
            $rows
        );
    }

    public function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($normalized === '') {
            return null;
        }

        return strtolower($normalized);
    }

    public function createByName(int $actorId, string $name): ?Role
    {
        $this->privileges()->assertRoleDefinitionCreate($actorId);

        $normalized = $this->normalizeName($name);
        if ($normalized === null) {
            return null;
        }

        $existingStmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $existingStmt->execute(['name' => $normalized]);
        if ((int) ($existingStmt->fetchColumn() ?: 0) > 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('INSERT INTO roles (name) VALUES (:name)');
        $stmt->execute(['name' => $normalized]);

        return new Role([
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $normalized,
        ]);
    }

    public function renameById(int $actorId, int $roleId, string $name): bool
    {
        $this->privileges()->assertRoleDefinitionChange($actorId, $roleId);

        $normalized = $this->normalizeName($name);
        if ($roleId <= 0 || $normalized === null) {
            return false;
        }

        $existingStmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $existingStmt->execute(['name' => $normalized]);
        $existingRoleId = (int) ($existingStmt->fetchColumn() ?: 0);
        if ($existingRoleId > 0 && $existingRoleId !== $roleId) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE roles SET name = :name WHERE id = :id');
        $stmt->execute([
            'id' => $roleId,
            'name' => $normalized,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $actorId, int $roleId): bool
    {
        $this->privileges()->assertRoleDefinitionChange($actorId, $roleId);

        if ($roleId <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $deletePermissionLinksStmt = $this->pdo->prepare(
                'DELETE FROM ' . self::ROLE_PERMISSION_TABLE . ' WHERE role_id = :roleId'
            );
            $deletePermissionLinksStmt->execute(['roleId' => $roleId]);

            $deleteUserLinksStmt = $this->pdo->prepare('DELETE FROM user_role WHERE role_id = :roleId');
            $deleteUserLinksStmt->execute(['roleId' => $roleId]);

            $deleteRoleStmt = $this->pdo->prepare('DELETE FROM roles WHERE id = :roleId');
            $deleteRoleStmt->execute(['roleId' => $roleId]);

            $this->pdo->commit();
            return $deleteRoleStmt->rowCount() > 0;
        } catch (\Throwable $e) {
            log()->error(sprintf('RoleRepository.deleteById failed: %s', $e->getMessage()), ['exception' => $e]);
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * @param array<int, int|string> $permissionIds
     * @return array<int, string>
     */
    public function updatePermissionsByRoleId(int $actorId, int $roleId, array $permissionIds): array
    {
        $this->privileges()->assertRolePermissionChange($actorId, $roleId, $permissionIds);

        if ($roleId <= 0) {
            return [];
        }

        $normalizedPermissionIds = array_values(array_unique(array_filter(array_map(
            static fn(mixed $permissionId): int => (int) $permissionId,
            $permissionIds
        ), static fn(int $permissionId): bool => $permissionId > 0)));

        $this->pdo->beginTransaction();
        try {
            $deleteStmt = $this->pdo->prepare(
                'DELETE FROM ' . self::ROLE_PERMISSION_TABLE . ' WHERE role_id = :roleId'
            );
            $deleteStmt->execute(['roleId' => $roleId]);

            if ($normalizedPermissionIds !== []) {
                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO ' . self::ROLE_PERMISSION_TABLE . ' (role_id, permission_id) VALUES (:roleId, :permissionId)'
                );

                foreach ($normalizedPermissionIds as $permissionId) {
                    $insertStmt->execute([
                        'roleId' => $roleId,
                        'permissionId' => $permissionId,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            log()->error(sprintf('RoleRepository.updatePermissionsByRoleId failed: %s', $e->getMessage()), ['exception' => $e]);
            $this->pdo->rollBack();
            return [];
        }

        if ($normalizedPermissionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedPermissionIds), '?'));
        $stmt = $this->pdo->prepare("SELECT name FROM permissions WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt->execute($normalizedPermissionIds);

        return array_values(array_filter(array_map(
            static fn(mixed $name): string => trim((string) $name),
            $stmt->fetchAll(\PDO::FETCH_COLUMN)
        )));
    }
}

<?php

declare(strict_types=1);

namespace NixPHP\CMS\Repositories;

use NixPHP\CMS\Contracts\UserPrivilegePolicyInterface;
use NixPHP\CMS\Models\Permission;
use NixPHP\CMS\Services\Security\UserPrivilegePolicy;
use NixPHP\ORM\Core\EntityManager;
use NixPHP\ORM\Repository\AbstractRepository;
use PDO;
use function NixPHP\ORM\em;
use function NixPHP\log;

class PermissionRepository extends AbstractRepository
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
        return Permission::class;
    }

    /**
     * @return array<int, array{id:int,name:string,role_count:int}>
     */
    public function findAllWithRoleCount(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                MIN(p.id) AS id,
                p.name,
                COUNT(DISTINCT rp.role_id) AS role_count
            FROM permissions p
            LEFT JOIN ' . self::ROLE_PERMISSION_TABLE . ' rp ON rp.permission_id = p.id
            GROUP BY p.name
            ORDER BY p.name ASC'
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'role_count' => (int) ($row['role_count'] ?? 0),
            ],
            $rows
        );
    }

    public function mergeDuplicateNames(): int
    {
        $duplicatesStmt = $this->pdo->query(
            'SELECT name FROM permissions GROUP BY name HAVING COUNT(*) > 1'
        );
        $duplicateNames = $duplicatesStmt->fetchAll(\PDO::FETCH_COLUMN);
        if ($duplicateNames === []) {
            return 0;
        }

        $removed = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($duplicateNames as $duplicateName) {
                $idsStmt = $this->pdo->prepare('SELECT id FROM permissions WHERE name = :name ORDER BY id ASC');
                $idsStmt->execute(['name' => (string) $duplicateName]);
                $ids = array_map('intval', $idsStmt->fetchAll(\PDO::FETCH_COLUMN));
                if (count($ids) < 2) {
                    continue;
                }

                $keepId = (int) array_shift($ids);
                foreach ($ids as $duplicateId) {
                    $mergeStmt = $this->pdo->prepare(
                        'INSERT IGNORE INTO ' . self::ROLE_PERMISSION_TABLE . ' (role_id, permission_id)
                         SELECT role_id, :keepId FROM ' . self::ROLE_PERMISSION_TABLE . ' WHERE permission_id = :duplicateId'
                    );
                    $mergeStmt->execute([
                        'keepId' => $keepId,
                        'duplicateId' => (int) $duplicateId,
                    ]);

                    $deleteRelationsStmt = $this->pdo->prepare(
                        'DELETE FROM ' . self::ROLE_PERMISSION_TABLE . ' WHERE permission_id = :duplicateId'
                    );
                    $deleteRelationsStmt->execute(['duplicateId' => (int) $duplicateId]);

                    $deletePermissionStmt = $this->pdo->prepare('DELETE FROM permissions WHERE id = :duplicateId');
                    $deletePermissionStmt->execute(['duplicateId' => (int) $duplicateId]);
                    $removed += $deletePermissionStmt->rowCount();
                }
            }

            $this->pdo->commit();
            return $removed;
        } catch (\Throwable $e) {
            log()->error(sprintf('PermissionRepository.mergeDuplicateNames failed: %s', $e->getMessage()), ['exception' => $e]);
            $this->pdo->rollBack();
            return 0;
        }
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

    public function createByName(int $actorId, string $name): ?Permission
    {
        $this->privileges()->assertPermissionDefinitionCreate($actorId);

        $normalized = $this->normalizeName($name);
        if ($normalized === null) {
            return null;
        }

        /** @var Permission|null $existing */
        $existing = $this->findOneBy(['name' => $normalized]);
        if ($existing instanceof Permission) {
            return null;
        }

        $permission = new Permission(['name' => $normalized]);
        em()->save($permission);

        return $permission;
    }

    public function renameById(int $actorId, int $permissionId, string $name): bool
    {
        $this->privileges()->assertPermissionDefinitionChange($actorId, $permissionId);

        $normalized = $this->normalizeName($name);
        if ($permissionId <= 0 || $normalized === null) {
            return false;
        }

        $existingStmt = $this->pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
        $existingStmt->execute(['name' => $normalized]);
        $existingId = (int) ($existingStmt->fetchColumn() ?: 0);
        if ($existingId > 0 && $existingId !== $permissionId) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE permissions SET name = :name WHERE id = :id');
        $stmt->execute([
            'id' => $permissionId,
            'name' => $normalized,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $actorId, int $permissionId): bool
    {
        $this->privileges()->assertPermissionDefinitionChange($actorId, $permissionId);

        if ($permissionId <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $deleteLinksStmt = $this->pdo->prepare(
                'DELETE FROM ' . self::ROLE_PERMISSION_TABLE . ' WHERE permission_id = :permissionId'
            );
            $deleteLinksStmt->execute(['permissionId' => $permissionId]);

            $deletePermissionStmt = $this->pdo->prepare('DELETE FROM permissions WHERE id = :permissionId');
            $deletePermissionStmt->execute(['permissionId' => $permissionId]);

            $this->pdo->commit();
            return $deletePermissionStmt->rowCount() > 0;
        } catch (\Throwable $e) {
            log()->error(sprintf('PermissionRepository.deleteById failed: %s', $e->getMessage()), ['exception' => $e]);
            $this->pdo->rollBack();
            return false;
        }
    }
}

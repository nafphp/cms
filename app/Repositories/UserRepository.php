<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use NixCMS\Enums\Permission as PermissionEnum;
use NixCMS\Models\User;
use NixCMS\Services\Security\CredentialService;
use DateInterval;
use DateTimeImmutable;
use NixPHP\ORM\Core\EntityManager;
use NixPHP\ORM\Repository\AbstractRepository;
use PDO;

class UserRepository extends AbstractRepository
{
    public function __construct(
        protected PDO $pdo,
        protected EntityManager $entityManager,
        private readonly CredentialService $credentialService,
    ) {
        parent::__construct($pdo, $entityManager);
    }

    protected function getEntityClass(): string
    {
        return User::class;
    }

    /**
     * @return array<int, PermissionEnum>
     */
    public function getPermissions(User $user): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT DISTINCT p.name
            FROM user_role ur
            JOIN role_permissions rp ON rp.role_id = ur.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :userId
        SQL);
        $stmt->execute(['userId' => $user->getId()]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $permissions = [];
        foreach ($names as $name) {
            $enum = PermissionEnum::tryFrom((string) $name);
            if ($enum !== null) {
                $permissions[$enum->value] = $enum;
            }
        }

        return array_values($permissions);
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * @return array<int, array{id:int,username:string,email:string,firstname:string,lastname:string,createdAt:string,roles:array<int,string>,permissions:array<int,string>}>
     */
    public function findAllWithRolesAndPermissions(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT
                u.id,
                u.username,
                COALESCE(u.email, '') AS email,
                COALESCE(u.firstname, '') AS firstname,
                COALESCE(u.lastname, '') AS lastname,
                COALESCE(u.createdAt, '') AS createdAt,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') AS role_names,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ',') AS permission_names
            FROM users u
            LEFT JOIN user_role ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            GROUP BY u.id, u.username, u.email, u.firstname, u.lastname, u.createdAt
            ORDER BY u.username ASC
        SQL);

        return array_map(
            static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'username' => (string) ($row['username'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'firstname' => (string) ($row['firstname'] ?? ''),
                    'lastname' => (string) ($row['lastname'] ?? ''),
                    'createdAt' => (string) ($row['createdAt'] ?? ''),
                    'roles' => self::csvList((string) ($row['role_names'] ?? '')),
                    'permissions' => self::csvList((string) ($row['permission_names'] ?? '')),
                ];
            },
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return array{id:int,username:string,email:string,firstname:string,lastname:string,roles:array<int,string>}|null
     */
    public function findOneForForm(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT
                u.id,
                u.username,
                COALESCE(u.email, '') AS email,
                COALESCE(u.firstname, '') AS firstname,
                COALESCE(u.lastname, '') AS lastname,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') AS role_names
            FROM users u
            LEFT JOIN user_role ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE u.id = :userId
            GROUP BY u.id, u.username, u.email, u.firstname, u.lastname
        SQL);
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'firstname' => (string) ($row['firstname'] ?? ''),
            'lastname' => (string) ($row['lastname'] ?? ''),
            'roles' => self::csvList((string) ($row['role_names'] ?? '')),
        ];
    }

    /**
     * @param array<int, mixed> $roles
     * @return array<int, string>
     */
    public function updateRolesByUserId(int $userId, array $roles): array
    {
        if ($userId <= 0) {
            return [];
        }

        $roleRows = $this->findRoleRowsByNames($roles);
        if ($roleRows === []) {
            return [];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM user_role WHERE user_id = :userId')->execute(['userId' => $userId]);

            $insert = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:userId, :roleId)');
            foreach ($roleRows as $roleRow) {
                $insert->execute([
                    'userId' => $userId,
                    'roleId' => (int) ($roleRow['id'] ?? 0),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [];
        }

        $assigned = array_map(static fn(array $row): string => (string) ($row['name'] ?? ''), $roleRows);
        sort($assigned);

        return $assigned;
    }

    public function upgradePasswordHash(int $userId, string $plainPassword): bool
    {
        if ($userId <= 0 || $plainPassword === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :userId');
        return $stmt->execute([
            'password' => $this->credentialService->hashPassword($plainPassword),
            'userId' => $userId,
        ]);
    }

    public function updatePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        if ($userId <= 0 || $currentPassword === '' || $newPassword === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT password FROM users WHERE id = :userId');
        $stmt->execute(['userId' => $userId]);
        $stored = $stmt->fetchColumn();

        if ($stored === false || !\password_verify($currentPassword, (string) $stored)) {
            return false;
        }

        $update = $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :userId');
        return $update->execute([
            'password' => $this->credentialService->hashPassword($newPassword),
            'userId' => $userId,
        ]);
    }

    /**
     * @return array<int, array{id:string,ip_address:?string,user_agent:?string,last_activity:int}>
     */
    public function getActiveSessions(int $userId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT id, ip_address, user_agent, last_activity
            FROM sessions
            WHERE user_id = :userId
            ORDER BY last_activity DESC
        SQL);
        $stmt->execute(['userId' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revokeSession(string $sessionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = :sessionId');
        return $stmt->execute(['sessionId' => $sessionId]);
    }

    public function revokeAllOtherSessions(int $userId, string $currentSessionId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions WHERE user_id = :userId AND id != :currentSessionId'
        );
        return $stmt->execute([
            'userId' => $userId,
            'currentSessionId' => $currentSessionId,
        ]);
    }

    /**
     * @param array<int,string> $roleNames
     */
    public function createWithRoles(
        string $username,
        string $password,
        ?string $firstname,
        ?string $lastname,
        array $roleNames,
        ?string $email = null,
    ): ?User {
        $username = trim($username);
        $password = trim($password);
        $email = $email !== null ? strtolower(trim($email)) : null;
        $firstname = $firstname !== null ? trim($firstname) : null;
        $lastname = $lastname !== null ? trim($lastname) : null;

        if ($username === '' || $password === '') {
            return null;
        }

        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $roleNames = array_values(array_unique(array_filter(array_map(
            static fn(mixed $role): string => trim((string) $role),
            $roleNames
        ))));

        if ($roleNames === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        $roleStmt = $this->pdo->prepare("SELECT id FROM roles WHERE name IN ($placeholders)");
        $roleStmt->execute($roleNames);
        $roleRows = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($roleRows === []) {
            return null;
        }

        $this->pdo->beginTransaction();
        try {
            $insertUser = $this->pdo->prepare(<<<SQL
                INSERT INTO users (username, password, email, firstname, lastname)
                VALUES (:username, :password, :email, :firstname, :lastname)
            SQL);
            $insertUser->execute([
                'username' => $username,
                'password' => $this->credentialService->hashPassword($password),
                'email' => $email !== null && $email !== '' ? $email : null,
                'firstname' => $firstname !== null && $firstname !== '' ? $firstname : null,
                'lastname' => $lastname !== null && $lastname !== '' ? $lastname : null,
            ]);

            $userId = (int) $this->pdo->lastInsertId();
            if ($userId <= 0) {
                $this->pdo->rollBack();
                return null;
            }

            $insertRole = $this->pdo->prepare(
                'INSERT INTO user_role (user_id, role_id) VALUES (:userId, :roleId)'
            );
            foreach ($roleRows as $row) {
                $insertRole->execute([
                    'userId' => $userId,
                    'roleId' => (int) ($row['id'] ?? 0),
                ]);
            }

            $this->pdo->commit();

            /** @var User|null $user */
            $user = $this->findOneBy('id', $userId);
            return $user;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        }
    }

    /**
     * @param array<int, string> $roleNames
     */
    public function updateWithRoles(
        int $userId,
        string $username,
        ?string $password,
        ?string $firstname,
        ?string $lastname,
        array $roleNames,
        ?string $email = null,
    ): bool {
        $username = trim($username);
        $password = $password !== null ? trim($password) : '';
        $email = $email !== null ? strtolower(trim($email)) : null;
        $firstname = $firstname !== null ? trim($firstname) : null;
        $lastname = $lastname !== null ? trim($lastname) : null;

        if ($userId <= 0 || $username === '') {
            return false;
        }

        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $roleRows = $this->findRoleRowsByNames($roleNames);
        if ($roleRows === []) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            if ($password !== '') {
                $updateUser = $this->pdo->prepare(<<<SQL
                    UPDATE users
                    SET username = :username,
                        password = :password,
                        email = :email,
                        firstname = :firstname,
                        lastname = :lastname
                    WHERE id = :userId
                SQL);
                $updateUser->execute([
                    'username' => $username,
                    'password' => $this->credentialService->hashPassword($password),
                    'email' => $email !== null && $email !== '' ? $email : null,
                    'firstname' => $firstname !== null && $firstname !== '' ? $firstname : null,
                    'lastname' => $lastname !== null && $lastname !== '' ? $lastname : null,
                    'userId' => $userId,
                ]);
            } else {
                $updateUser = $this->pdo->prepare(<<<SQL
                    UPDATE users
                    SET username = :username,
                        email = :email,
                        firstname = :firstname,
                        lastname = :lastname
                    WHERE id = :userId
                SQL);
                $updateUser->execute([
                    'username' => $username,
                    'email' => $email !== null && $email !== '' ? $email : null,
                    'firstname' => $firstname !== null && $firstname !== '' ? $firstname : null,
                    'lastname' => $lastname !== null && $lastname !== '' ? $lastname : null,
                    'userId' => $userId,
                ]);
            }

            $this->pdo->prepare('DELETE FROM user_role WHERE user_id = :userId')->execute(['userId' => $userId]);
            $insertRole = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:userId, :roleId)');
            foreach ($roleRows as $roleRow) {
                $insertRole->execute([
                    'userId' => $userId,
                    'roleId' => (int) ($roleRow['id'] ?? 0),
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function deleteById(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM user_role WHERE user_id = :userId')->execute(['userId' => $userId]);
            $this->pdo->prepare('DELETE FROM sessions WHERE user_id = :userId')->execute(['userId' => $userId]);
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :userId');
            $stmt->execute(['userId' => $userId]);

            $this->pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * @param array<int, mixed> $roles
     * @return array{token:string,email:string,roles:array<int,string>,expiresAt:string}|null
     */
    public function createInvite(
        string $email,
        array $roles,
        ?int $createdByUserId,
        int $expiresInDays = 7,
        bool $canAssignAdminRole = false,
    ): ?array {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $roleNames = $this->normalizeInviteRoles($roles, $canAssignAdminRole);
        $roleRows = $this->findRoleRowsByNames($roleNames);
        if ($roleNames === [] || count($roleRows) !== count($roleNames)) {
            return null;
        }
        $roleNames = array_map(static fn(array $roleRow): string => (string) ($roleRow['name'] ?? ''), $roleRows);
        sort($roleNames);

        $expiresInDays = max(1, min(30, $expiresInDays));
        $expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval('P' . $expiresInDays . 'D'));
        $token = bin2hex(random_bytes(32));
        $rolesJson = json_encode($roleNames);
        if ($rolesJson === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO user_invites (token_hash, email, roles_json, created_by_user_id, expires_at)
            VALUES (:tokenHash, :email, :rolesJson, :createdByUserId, :expiresAt)
        SQL);
        $stmt->execute([
            'tokenHash' => hash('sha256', $token),
            'email' => $email,
            'rolesJson' => $rolesJson,
            'createdByUserId' => $createdByUserId !== null && $createdByUserId > 0 ? $createdByUserId : null,
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'email' => $email,
            'roles' => $roleNames,
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{id:int,email:string,roles:array<int,string>,expires_at:string,used_at:?string}|null
     */
    public function findInviteByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT id, email, roles_json, expires_at, used_at
            FROM user_invites
            WHERE token_hash = :tokenHash
            LIMIT 1
        SQL);
        $stmt->execute(['tokenHash' => hash('sha256', $token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $roles = json_decode((string) ($row['roles_json'] ?? '[]'), true);
        $roles = is_array($roles) ? $this->normalizeInviteRoles($roles, true) : [];
        if ($roles === []) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'email' => strtolower(trim((string) ($row['email'] ?? ''))),
            'roles' => $roles,
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'used_at' => $row['used_at'] !== null ? (string) $row['used_at'] : null,
        ];
    }

    /**
     * @return array{user:?User,reason:?string}
     */
    public function registerUserWithInvite(
        string $token,
        string $email,
        string $username,
        string $password,
        ?string $firstname,
        ?string $lastname,
    ): array {
        $token = trim($token);
        $email = strtolower(trim($email));
        $username = trim($username);
        $password = trim($password);
        $firstname = $firstname !== null ? trim($firstname) : null;
        $lastname = $lastname !== null ? trim($lastname) : null;

        if ($token === '' || $email === '' || $username === '' || $password === '') {
            return ['user' => null, 'reason' => 'invalid_input'];
        }

        $this->pdo->beginTransaction();
        try {
            $inviteStmt = $this->pdo->prepare(<<<SQL
                SELECT id, email, roles_json, expires_at, used_at
                FROM user_invites
                WHERE token_hash = :tokenHash
                LIMIT 1
                FOR UPDATE
            SQL);
            $inviteStmt->execute(['tokenHash' => hash('sha256', $token)]);
            $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($invite)) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'invalid_invite'];
            }

            if (!empty($invite['used_at'])) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'invite_used'];
            }

            $expiresAt = strtotime((string) ($invite['expires_at'] ?? ''));
            if ($expiresAt === false || $expiresAt < time()) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'invite_expired'];
            }

            if (strtolower(trim((string) ($invite['email'] ?? ''))) !== $email) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'invite_email_mismatch'];
            }

            if ($this->usernameExistsForAnotherUser($username)) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'username_exists'];
            }

            if ($this->emailExistsForAnotherUser($email)) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'email_exists'];
            }

            $decodedRoles = json_decode((string) ($invite['roles_json'] ?? '[]'), true);
            $roles = is_array($decodedRoles) ? $this->normalizeInviteRoles($decodedRoles, true) : [];
            $roleRows = $this->findRoleRowsByNames($roles);
            if ($roleRows === []) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'invalid_roles'];
            }

            $insertUser = $this->pdo->prepare(<<<SQL
                INSERT INTO users (username, password, email, firstname, lastname)
                VALUES (:username, :password, :email, :firstname, :lastname)
            SQL);
            $insertUser->execute([
                'username' => $username,
                'password' => $this->credentialService->hashPassword($password),
                'email' => $email,
                'firstname' => $firstname !== null && $firstname !== '' ? $firstname : null,
                'lastname' => $lastname !== null && $lastname !== '' ? $lastname : null,
            ]);

            $userId = (int) $this->pdo->lastInsertId();
            if ($userId <= 0) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'create_failed'];
            }

            $insertRole = $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (:userId, :roleId)');
            foreach ($roleRows as $roleRow) {
                $insertRole->execute([
                    'userId' => $userId,
                    'roleId' => (int) ($roleRow['id'] ?? 0),
                ]);
            }

            $consumeInvite = $this->pdo->prepare(<<<SQL
                UPDATE user_invites
                SET used_at = CURRENT_TIMESTAMP
                WHERE id = :inviteId
                  AND used_at IS NULL
            SQL);
            $consumeInvite->execute(['inviteId' => (int) ($invite['id'] ?? 0)]);
            if ($consumeInvite->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['user' => null, 'reason' => 'invite_used'];
            }

            $this->pdo->commit();

            /** @var User|null $user */
            $user = $this->findOneBy('id', $userId);
            return ['user' => $user, 'reason' => null];
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['user' => null, 'reason' => 'create_failed'];
        }
    }

    public function usernameExistsForAnotherUser(string $username, ?int $userId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => trim($username)]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        return $existingId > 0 && ($userId === null || $existingId !== $userId);
    }

    public function emailExistsForAnotherUser(string $email, ?int $userId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        return $existingId > 0 && ($userId === null || $existingId !== $userId);
    }

    /**
     * @param array<int, mixed> $roleNames
     * @return array<int, array{id:int,name:string}>
     */
    private function findRoleRowsByNames(array $roleNames): array
    {
        $roleNames = array_values(array_unique(array_filter(array_map(
            static fn(mixed $role): string => trim((string) $role),
            $roleNames
        ))));

        if ($roleNames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name FROM roles WHERE name IN ($placeholders)");
        $stmt->execute($roleNames);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, mixed> $roles
     * @return array<int, string>
     */
    private function normalizeInviteRoles(array $roles, bool $canAssignAdminRole): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn(mixed $role): string => strtolower(trim((string) $role)),
            $roles
        ))));

        if (!$canAssignAdminRole) {
            $normalized = array_values(array_filter(
                $normalized,
                static fn(string $role): bool => $role !== 'admin'
            ));
        }

        sort($normalized);

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private static function csvList(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn(string $item): string => trim($item),
            explode(',', $value)
        )));
    }
}

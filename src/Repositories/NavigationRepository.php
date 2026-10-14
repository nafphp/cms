<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use InvalidArgumentException;
use Naf\CMS\Navigation\Navigation;
use PDO;
use RuntimeException;
use Throwable;

use function Naf\Database\database;
use function Naf\log;

final class NavigationRepository
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaries(string $scope = 'frontend'): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $statement = $connection->prepare(<<<SQL
                SELECT id, identifier, title, scope, language, status, items_json, created_at, updated_at
                FROM navigations
                WHERE scope = :scope
                ORDER BY title ASC, identifier ASC
            SQL);
            $statement->execute(['scope' => trim($scope) ?: 'frontend']);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error('NavigationRepository.summaries failed', [
                'exception' => $e,
                'scope'     => $scope,
            ]);

            return [];
        }

        return array_map([$this, 'summaryFromRow'], is_array($rows) ? $rows : []);
    }

    public function find(int $id): ?Navigation
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT * FROM navigations WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error('NavigationRepository.find failed', [
                'exception' => $e,
                'id'        => $id,
            ]);

            return null;
        }

        return is_array($row) ? $this->navigationFromRow($row) : null;
    }

    /**
     * @return array<string, Navigation>
     */
    public function findTranslations(string $identifier, string $scope = 'frontend'): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $identifier = Navigation::normalizeIdentifier($identifier);
            $statement  = $connection->prepare(<<<SQL
                SELECT * FROM navigations
                WHERE identifier = :identifier AND scope = :scope
                ORDER BY language ASC
            SQL);
            $statement->execute([
                'identifier' => $identifier,
                'scope'      => trim($scope) ?: 'frontend',
            ]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error('NavigationRepository.findTranslations failed', [
                'exception'  => $e,
                'identifier' => $identifier,
                'scope'      => $scope,
            ]);

            return [];
        }

        $translations = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $navigation                                  = $this->navigationFromRow($row);
            $translations[$navigation->language() ?? ''] = $navigation;
        }

        return $translations;
    }

    public function findByIdentifier(string $identifier, string $scope = 'frontend', ?string $language = null): ?Navigation
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return null;
        }

        try {
            $identifier = Navigation::normalizeIdentifier($identifier);
        } catch (InvalidArgumentException) {
            return null;
        }

        $scope    = trim($scope) ?: 'frontend';
        $language = $language === null ? null : strtolower(trim($language));

        try {
            if ($language !== null && $language !== '') {
                $statement = $connection->prepare(<<<SQL
                    SELECT * FROM navigations
                    WHERE identifier = :identifier AND scope = :scope AND language = :language
                    LIMIT 1
                SQL);
                $statement->execute([
                    'identifier' => $identifier,
                    'scope'      => $scope,
                    'language'   => $language,
                ]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $this->navigationFromRow($row);
                }
            }

            $statement = $connection->prepare(<<<SQL
                SELECT * FROM navigations
                WHERE identifier = :identifier AND scope = :scope AND language = ''
                LIMIT 1
            SQL);
            $statement->execute([
                'identifier' => $identifier,
                'scope'      => $scope,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $this->navigationFromRow($row);
            }

            $statement = $connection->prepare(<<<SQL
                SELECT * FROM navigations
                WHERE identifier = :identifier AND scope = :scope
                ORDER BY
                    CASE status
                        WHEN 'primary' THEN 0
                        WHEN 'published' THEN 1
                        ELSE 2
                    END,
                    language ASC,
                    updated_at DESC,
                    created_at DESC
                LIMIT 1
            SQL);
            $statement->execute([
                'identifier' => $identifier,
                'scope'      => $scope,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error('NavigationRepository.findByIdentifier failed', [
                'exception'  => $e,
                'identifier' => $identifier,
                'scope'      => $scope,
                'language'   => $language,
            ]);

            return null;
        }

        return is_array($row) ? $this->navigationFromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('No database connection available.');
        }

        $id         = (int) ($payload['id'] ?? 0);
        $identifier = Navigation::normalizeIdentifier((string) ($payload['identifier'] ?? ''));
        $title      = trim((string) ($payload['title'] ?? ''));
        $scope      = trim((string) ($payload['scope'] ?? 'frontend')) ?: 'frontend';
        $language   = strtolower(trim((string) ($payload['language'] ?? '')));
        $status     = trim((string) ($payload['status'] ?? 'draft')) ?: 'draft';
        $items      = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($title === '') {
            $title = ucwords(str_replace(['-', '_', '.'], ' ', $identifier));
        }

        $itemsJson = json_encode(array_values($items), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $params    = [
            'identifier' => $identifier,
            'title'      => $title,
            'scope'      => $scope,
            'language'   => $language,
            'status'     => $status,
            'items_json' => $itemsJson,
        ];

        if ($id > 0) {
            $statement = $connection->prepare(<<<SQL
                UPDATE navigations
                SET identifier = :identifier,
                    title = :title,
                    scope = :scope,
                    language = :language,
                    status = :status,
                    items_json = :items_json,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            SQL);
            $statement->execute($params + ['id' => $id]);

            return $id;
        }

        $existingStatement = $connection->prepare(<<<SQL
            SELECT id FROM navigations
            WHERE identifier = :identifier AND scope = :scope AND language = :language
            LIMIT 1
        SQL);
        $existingStatement->execute([
            'identifier' => $identifier,
            'scope'      => $scope,
            'language'   => $language,
        ]);
        $existingId = (int) ($existingStatement->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $this->save(array_replace($payload, ['id' => $existingId]));
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO navigations (identifier, title, scope, language, status, items_json, created_at, updated_at)
            VALUES (:identifier, :title, :scope, :language, :status, :items_json, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);
        $statement->execute($params);

        return (int) $connection->lastInsertId();
    }

    public function publish(int $id): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return false;
        }

        $statement = $connection->prepare(<<<SQL
            UPDATE navigations
            SET status = 'published', updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        SQL);
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return false;
        }

        $statement = $connection->prepare('DELETE FROM navigations WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function deleteTranslationsExcept(string $identifier, string $scope = 'frontend', int $keepId = 0): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return 0;
        }

        try {
            $identifier = Navigation::normalizeIdentifier($identifier);
        } catch (InvalidArgumentException) {
            return 0;
        }

        $statement = $connection->prepare(<<<SQL
            DELETE FROM navigations
            WHERE identifier = :identifier
              AND scope = :scope
              AND id != :keep_id
        SQL);
        $statement->execute([
            'identifier' => $identifier,
            'scope'      => trim($scope) ?: 'frontend',
            'keep_id'    => $keepId,
        ]);

        return $statement->rowCount();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function summaryFromRow(array $row): array
    {
        $items = $this->decodeItems((string) ($row['items_json'] ?? '[]'));

        return [
            'id'         => (int) ($row['id'] ?? 0),
            'identifier' => (string) ($row['identifier'] ?? ''),
            'title'      => (string) ($row['title'] ?? ''),
            'scope'      => (string) ($row['scope'] ?? 'frontend'),
            'language'   => (string) ($row['language'] ?? ''),
            'status'     => (string) ($row['status'] ?? 'draft'),
            'item_count' => $this->countItems($items),
            'updated'    => (string) (($row['updated_at'] ?? null) ?: ($row['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function navigationFromRow(array $row): Navigation
    {
        return Navigation::fromArray([
            'id'         => (int) ($row['id'] ?? 0),
            'identifier' => (string) ($row['identifier'] ?? ''),
            'title'      => (string) ($row['title'] ?? ''),
            'scope'      => (string) ($row['scope'] ?? 'frontend'),
            'language'   => $row['language'] ?? null,
            'status'     => (string) ($row['status'] ?? 'draft'),
            'items'      => $this->decodeItems((string) ($row['items_json'] ?? '[]')),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeItems(string $itemsJson): array
    {
        try {
            $decoded = json_decode($itemsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            log()->warning('NavigationRepository.decodeItems failed', [
                'exception' => $e,
            ]);

            return [];
        }

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function countItems(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $count++;
            $count += $this->countItems(is_array($item['children'] ?? null) ? $item['children'] : []);
        }

        return $count;
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $connection = database();
        } catch (Throwable $e) {
            log()->error('NavigationRepository.connection failed', [
                'exception' => $e,
            ]);

            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }
}

<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use Naf\CMS\Contracts\AuditSinkInterface;
use Naf\CMS\Support\AuditLogRecord;
use PDO;
use Throwable;

use function Naf\Database\database;
use function Naf\log;

final class AuditLogRepository implements AuditSinkInterface
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function record(array $entry): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return false;
        }

        try {
            $hasEntryJson         = $this->columnExists($connection, 'audit_log', 'entry_json');
            $entryJsonColumn      = $hasEntryJson ? "entry_json,\n                    " : '';
            $entryJsonPlaceholder = $hasEntryJson ? ":entry_json,\n                    " : '';
            $statement            = $connection->prepare(<<<SQL
                INSERT INTO audit_log (
                    {$entryJsonColumn}
                    actor_user_id,
                    actor_label,
                    action,
                    subject_type,
                    subject_key,
                    old_value,
                    new_value,
                    meta_json,
                    ip_address,
                    created_at
                ) VALUES (
                    {$entryJsonPlaceholder}
                    :actor_user_id,
                    :actor_label,
                    :action,
                    :subject_type,
                    :subject_key,
                    :old_value,
                    :new_value,
                    :meta_json,
                    :ip_address,
                    CURRENT_TIMESTAMP
                )
            SQL);

            $parameters = [
                'actor_user_id' => $entry['actor_user_id'] ?? null,
                'actor_label'   => (string) ($entry['actor_label'] ?? ''),
                'action'        => (string) ($entry['action'] ?? ''),
                'subject_type'  => (string) ($entry['subject_type'] ?? ''),
                'subject_key'   => (string) ($entry['subject_key'] ?? ''),
                'old_value'     => $this->encodeValue($entry['old_value'] ?? null),
                'new_value'     => $this->encodeValue($entry['new_value'] ?? null),
                'meta_json'     => $this->encodeValue($entry['meta'] ?? []),
                'ip_address'    => (string) ($entry['ip_address'] ?? ''),
            ];

            if ($hasEntryJson) {
                $parameters['entry_json'] = $this->encodeValue($this->entryDocument($entry));
            }

            return $statement->execute($parameters);
        } catch (Throwable $e) {
            log()->error(sprintf('AuditLogRepository.record failed: %s', $e->getMessage()), ['exception' => $e]);

            return false;
        }
    }

    /** @return list<AuditLogRecord> */
    public function latestRecords(int $limit = 100, ?string $subjectType = null): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        $limit  = max(1, min(250, $limit));
        $where  = '';
        $params = [];
        if ($subjectType !== null && trim($subjectType) !== '') {
            $where                  = 'WHERE subject_type = :subject_type';
            $params['subject_type'] = trim($subjectType);
        }

        $entryJsonSelect = $this->columnExists($connection, 'audit_log', 'entry_json')
            ? 'entry_json,'
            : "NULL AS entry_json,";

        try {
            $statement = $connection->prepare(<<<SQL
                SELECT
                    id,
                    {$entryJsonSelect}
                    actor_user_id,
                    actor_label,
                    action,
                    subject_type,
                    subject_key,
                    old_value,
                    new_value,
                    meta_json,
                    ip_address,
                    created_at
                FROM audit_log
                {$where}
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}
            SQL);
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            log()->error(sprintf('AuditLogRepository.latestRecords failed: %s', $e->getMessage()), ['exception' => $e]);

            return [];
        }

        return array_map(
            static fn(array $row): AuditLogRecord => AuditLogRecord::fromDatabaseRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 100, ?string $subjectType = null): array
    {
        return array_map(
            static fn(AuditLogRecord $record): array => $record->toArray(),
            $this->latestRecords($limit, $subjectType),
        );
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function entryDocument(array $entry): array
    {
        return [
            'schema' => 'nafcms.audit.v1',
            'actor'  => [
                'user_id' => $entry['actor_user_id'] ?? null,
                'label'   => (string) ($entry['actor_label'] ?? ''),
            ],
            'event' => [
                'action'       => (string) ($entry['action'] ?? ''),
                'subject_type' => (string) ($entry['subject_type'] ?? ''),
                'subject_key'  => (string) ($entry['subject_key'] ?? ''),
                'old_value'    => $entry['old_value'] ?? null,
                'new_value'    => $entry['new_value'] ?? null,
                'meta'         => $entry['meta'] ?? [],
            ],
            'request' => [
                'ip_address' => (string) ($entry['ip_address'] ?? ''),
            ],
        ];
    }

    private function encodeValue(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
    }

    private function decodeValue(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            log()->warning(sprintf('AuditLogRepository.decodeValue failed: %s', $e->getMessage()), ['exception' => $e]);

            return $value;
        }
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $connection = database();
        } catch (Throwable $e) {
            log()->error(sprintf('AuditLogRepository.connection failed: %s', $e->getMessage()), ['exception' => $e]);

            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }

    private function columnExists(PDO $connection, string $table, string $column): bool
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $statement = $connection->query('PRAGMA table_info(' . $table . ')');
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string) ($row['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $connection->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $statement->execute(['column' => $column]);

        return (bool) $statement->fetchColumn();
    }
}

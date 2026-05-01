<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use PDO;
use function NixPHP\Database\database;

final class AuditLogRepository
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
            $statement = $connection->prepare(<<<SQL
                INSERT INTO audit_log (
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

            return $statement->execute([
                'actor_user_id' => $entry['actor_user_id'] ?? null,
                'actor_label' => (string) ($entry['actor_label'] ?? ''),
                'action' => (string) ($entry['action'] ?? ''),
                'subject_type' => (string) ($entry['subject_type'] ?? ''),
                'subject_key' => (string) ($entry['subject_key'] ?? ''),
                'old_value' => $this->encodeValue($entry['old_value'] ?? null),
                'new_value' => $this->encodeValue($entry['new_value'] ?? null),
                'meta_json' => $this->encodeValue($entry['meta'] ?? []),
                'ip_address' => (string) ($entry['ip_address'] ?? ''),
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 100, ?string $subjectType = null): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        $limit = max(1, min(250, $limit));
        $where = '';
        $params = [];
        if ($subjectType !== null && trim($subjectType) !== '') {
            $where = 'WHERE subject_type = :subject_type';
            $params['subject_type'] = trim($subjectType);
        }

        try {
            $statement = $connection->prepare(<<<SQL
                SELECT
                    id,
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
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn(array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'actor_user_id' => $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
            'actor_label' => (string) ($row['actor_label'] ?? ''),
            'action' => (string) ($row['action'] ?? ''),
            'subject_type' => (string) ($row['subject_type'] ?? ''),
            'subject_key' => (string) ($row['subject_key'] ?? ''),
            'old_value' => $this->decodeValue((string) ($row['old_value'] ?? 'null')),
            'new_value' => $this->decodeValue((string) ($row['new_value'] ?? 'null')),
            'meta' => $this->decodeValue((string) ($row['meta_json'] ?? '[]')),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ], is_array($rows) ? $rows : []);
    }

    private function encodeValue(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
    }

    private function decodeValue(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
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
        } catch (\Throwable) {
            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }
}

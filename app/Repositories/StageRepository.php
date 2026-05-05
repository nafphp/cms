<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use NixCMS\Stage\Stage;
use NixCMS\Stage\StageChange;
use PDO;
use function NixPHP\Database\database;

final class StageRepository
{
    public function __construct(private ?PDO $connection = null) {}

    public function save(Stage $stage): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('No database connection available.');
        }

        $id = (int) ($stage->id() ?? 0);
        $payload = [
            'target_type' => $stage->target()->type(),
            'target_id' => $stage->target()->id(),
            'status' => $stage->status(),
            'base_snapshot_json' => $this->encode($stage->baseSnapshot()),
            'working_snapshot_json' => $this->encode($stage->workingSnapshot()),
            'changes_json' => $this->encode(array_map(static fn(StageChange $change): array => $change->toArray(), $stage->changes())),
            'ui_actions_json' => $this->encode($stage->uiActions()),
            'warnings_json' => $this->encode($stage->warnings()),
        ];

        if ($id > 0 && $this->find($id) !== null) {
            $statement = $connection->prepare(<<<SQL
                UPDATE cms_stages
                SET target_type = :target_type,
                    target_id = :target_id,
                    status = :status,
                    base_snapshot_json = :base_snapshot_json,
                    working_snapshot_json = :working_snapshot_json,
                    changes_json = :changes_json,
                    ui_actions_json = :ui_actions_json,
                    warnings_json = :warnings_json,
                    committed_at = CASE WHEN :committed_status = 'committed' THEN CURRENT_TIMESTAMP ELSE committed_at END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            SQL);
            $statement->execute($payload + ['id' => $id, 'committed_status' => $stage->status()]);

            return $id;
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO cms_stages (
                target_type,
                target_id,
                status,
                base_snapshot_json,
                working_snapshot_json,
                changes_json,
                ui_actions_json,
                warnings_json,
                created_at,
                updated_at
            ) VALUES (
                :target_type,
                :target_id,
                :status,
                :base_snapshot_json,
                :working_snapshot_json,
                :changes_json,
                :ui_actions_json,
                :warnings_json,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        SQL);
        $statement->execute($payload);

        return (int) $connection->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT * FROM cms_stages WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        return [
            ...$row,
            'id' => (int) ($row['id'] ?? 0),
            'base_snapshot' => $this->decode((string) ($row['base_snapshot_json'] ?? '{}')),
            'working_snapshot' => $this->decode((string) ($row['working_snapshot_json'] ?? '{}')),
            'changes' => $this->decode((string) ($row['changes_json'] ?? '[]')),
            'ui_actions' => $this->decode((string) ($row['ui_actions_json'] ?? '[]')),
            'warnings' => $this->decode((string) ($row['warnings_json'] ?? '[]')),
        ];
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
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

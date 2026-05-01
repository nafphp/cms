<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use PDO;
use function NixPHP\Database\database;

final class SettingRepository
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    public function value(string $key): ?string
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || trim($key) === '') {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT value FROM cms_settings WHERE setting_key = :setting_key LIMIT 1');
            $statement->execute(['setting_key' => trim($key)]);
            $value = $statement->fetchColumn();
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $rows = $connection->query('SELECT setting_key, value FROM cms_settings ORDER BY setting_key ASC')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['setting_key'] ?? ''));
            if ($key !== '') {
                $settings[$key] = (string) ($row['value'] ?? '');
            }
        }

        return $settings;
    }

    /** @param array<string, string> $values */
    public function saveMany(array $values): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return false;
        }

        try {
            $connection->beginTransaction();
            foreach ($values as $key => $value) {
                $this->save($key, $value, $connection);
            }
            $connection->commit();
        } catch (\Throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            return false;
        }

        return true;
    }

    private function save(string $key, string $value, PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $statement = $connection->prepare(<<<SQL
                INSERT INTO cms_settings (setting_key, value, updated_at)
                VALUES (:setting_key, :value, CURRENT_TIMESTAMP)
                ON CONFLICT(setting_key) DO UPDATE SET
                    value = excluded.value,
                    updated_at = CURRENT_TIMESTAMP
            SQL);
        } else {
            $statement = $connection->prepare(<<<SQL
                INSERT INTO cms_settings (setting_key, value, updated_at)
                VALUES (:setting_key, :value, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value),
                    updated_at = CURRENT_TIMESTAMP
            SQL);
        }

        $statement->execute([
            'setting_key' => trim($key),
            'value' => $value,
        ]);
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

<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202605030003 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        if (!$this->tableExists($connection, 'audit_log') || $this->columnExists($connection, 'audit_log', 'entry_json')) {
            return;
        }

        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $type   = $driver === 'sqlite' ? 'TEXT' : 'JSON';
        $connection->exec("ALTER TABLE audit_log ADD COLUMN entry_json {$type} NULL");
    }

    public function down(PDO $connection): void
    {
        // Keep audit payloads intact on rollback.
    }

    private function tableExists(PDO $connection, string $table): bool
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $statement = $connection->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
            $statement->execute(['table' => $table]);

            return (bool) $statement->fetchColumn();
        }

        $statement = $connection->prepare('SHOW TABLES LIKE :table');
        $statement->execute(['table' => $table]);

        return (bool) $statement->fetchColumn();
    }

    private function columnExists(PDO $connection, string $table, string $column): bool
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $rows = $connection->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach (is_array($rows) ? $rows : [] as $row) {
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

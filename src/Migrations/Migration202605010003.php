<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202605010003 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver   = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';

        if ($driver === 'sqlite') {
            $connection->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS audit_log (
                    {$idColumn},
                    actor_user_id INT NULL,
                    actor_label VARCHAR(190) NOT NULL,
                    action VARCHAR(120) NOT NULL,
                    subject_type VARCHAR(120) NOT NULL,
                    subject_key VARCHAR(190) NOT NULL,
                    old_value TEXT NOT NULL,
                    new_value TEXT NOT NULL,
                    meta_json TEXT NOT NULL,
                    ip_address VARCHAR(64) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_subject ON audit_log (subject_type, subject_key)');
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log (created_at)');
            $connection->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_actor_user_id ON audit_log (actor_user_id)');

            return;
        }

        $connection->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS audit_log (
                {$idColumn},
                actor_user_id INT NULL,
                actor_label VARCHAR(190) NOT NULL,
                action VARCHAR(120) NOT NULL,
                subject_type VARCHAR(120) NOT NULL,
                subject_key VARCHAR(190) NOT NULL,
                old_value TEXT NOT NULL,
                new_value TEXT NOT NULL,
                meta_json TEXT NOT NULL,
                ip_address VARCHAR(64) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_log_subject (subject_type, subject_key),
                INDEX idx_audit_log_created_at (created_at),
                INDEX idx_audit_log_actor_user_id (actor_user_id)
            )
        SQL);
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS audit_log');
    }
}

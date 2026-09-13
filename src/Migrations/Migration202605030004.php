<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202605030004 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $idColumn = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id INT AUTO_INCREMENT PRIMARY KEY';
        $jsonType = $driver === 'sqlite' ? 'TEXT' : 'JSON';
        $uniqueSyntax = $driver === 'sqlite' ? 'IF NOT EXISTS ' : '';

        if ($this->tableExists($connection, 'page_layouts')) {
            return;
        }

        $connection->exec(<<<SQL
            CREATE TABLE page_layouts (
                {$idColumn},
                identifier VARCHAR(160) NOT NULL,
                name VARCHAR(190) NOT NULL,
                description TEXT NULL,
                layout VARCHAR(120) NOT NULL DEFAULT 'layouts.page',
                view VARCHAR(160) NOT NULL DEFAULT 'page.templates.custom',
                slots_json {$jsonType} NOT NULL,
                meta_json {$jsonType} NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                CONSTRAINT uq_page_layouts_identifier UNIQUE (identifier)
            )
        SQL);

        $connection->exec("CREATE INDEX {$uniqueSyntax}idx_page_layouts_name ON page_layouts (name)");
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS page_layouts');
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
}

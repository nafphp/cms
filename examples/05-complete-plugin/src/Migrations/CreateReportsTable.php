<?php

declare(strict_types=1);

namespace ExampleVendor\ReportPlugin\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class CreateReportsTable extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $id = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $connection->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS example_plugin_reports (id %s, title VARCHAR(190) NOT NULL)',
            $id,
        ));
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS example_plugin_reports');
    }
}

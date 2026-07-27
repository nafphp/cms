<?php

declare(strict_types=1);

namespace NixPHP\CMS\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;

final class Migration202605030005 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec('ALTER TABLE pages ADD COLUMN theme_id VARCHAR(120) NULL');
    }

    public function down(PDO $connection): void
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            return;
        }

        $connection->exec('ALTER TABLE pages DROP COLUMN theme_id');
    }
}

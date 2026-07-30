<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use NixPHP\Database\Core\MigrationInterface;
use Tests\Support\DatabaseTestCase;

final class PluginMigrationsSqliteTest extends DatabaseTestCase
{
    public function testAllPluginMigrationsRunOnFreshSqliteDatabase(): void
    {
        $files = glob(BASE_PATH . '/src/Migrations/Migration*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            require_once $file;
            $class = 'NixPHP\\CMS\\Migrations\\' . basename($file, '.php');
            $migration = new $class();

            self::assertInstanceOf(MigrationInterface::class, $migration);
            $migration->up($this->pdo);
        }

        $tables = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach (['users', 'articles', 'article_versions', 'media', 'pages', 'forms', 'themes'] as $table) {
            self::assertContains($table, $tables);
        }
    }
}

<?php

declare(strict_types=1);

namespace Naf\CMS\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class Migration202605030002 extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        if (!$this->tableExists($connection, 'media')) {
            return;
        }

        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if (!$this->columnExists($connection, 'media', 'public_id')) {
            $connection->exec('ALTER TABLE media ADD COLUMN public_id VARCHAR(40) NULL');
        }

        if (!$this->columnExists($connection, 'media', 'visibility')) {
            $connection->exec("ALTER TABLE media ADD COLUMN visibility VARCHAR(24) NOT NULL DEFAULT 'public'");
        }

        $this->backfillPublicIds($connection);
        $connection->exec("UPDATE media SET visibility = 'registered' WHERE visibility = 'admin'");
        $this->moveExistingPublicMedia();
        $this->rewriteUrls($connection);

        if (!$this->indexExists($connection, 'media', 'idx_media_visibility')) {
            $connection->exec($driver === 'sqlite'
                ? 'CREATE INDEX IF NOT EXISTS idx_media_visibility ON media (visibility)'
                : 'CREATE INDEX idx_media_visibility ON media (visibility)');
        }

        if (!$this->indexExists($connection, 'media', 'uq_media_public_id')) {
            $connection->exec($driver === 'sqlite'
                ? 'CREATE UNIQUE INDEX IF NOT EXISTS uq_media_public_id ON media (public_id)'
                : 'CREATE UNIQUE INDEX uq_media_public_id ON media (public_id)');
        }
    }

    public function down(PDO $connection): void
    {
        // Compatibility migration only; leave migrated media metadata intact.
    }

    private function backfillPublicIds(PDO $connection): void
    {
        $rows = $connection->query("SELECT id, public_id FROM media WHERE public_id IS NULL OR public_id = ''")
            ->fetchAll(PDO::FETCH_ASSOC);
        $update = $connection->prepare('UPDATE media SET public_id = :public_id WHERE id = :id');

        foreach (is_array($rows) ? $rows : [] as $row) {
            $update->execute([
                'id' => (int) ($row['id'] ?? 0),
                'public_id' => $this->newPublicId($connection),
            ]);
        }
    }

    private function rewriteUrls(PDO $connection): void
    {
        $rows = $connection->query('SELECT id, public_id, stored_name, storage_path FROM media')
            ->fetchAll(PDO::FETCH_ASSOC);
        $update = $connection->prepare(<<<SQL
            UPDATE media
            SET public_url = :public_url,
                file_path = :file_path,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        SQL);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $storedName = (string) ($row['stored_name'] ?? '');
            $publicId = (string) ($row['public_id'] ?? '');
            if ($storedName === '' || $publicId === '') {
                continue;
            }

            $storagePath = trim((string) ($row['storage_path'] ?? ''), '/');
            $update->execute([
                'id' => (int) ($row['id'] ?? 0),
                'public_url' => '/media/' . $publicId . '/' . rawurlencode($storedName),
                'file_path' => 'storage/media/' . $storagePath,
            ]);
        }
    }

    private function moveExistingPublicMedia(): void
    {
        if (!defined('BASE_PATH')) {
            return;
        }

        $source = BASE_PATH . '/public/media';
        $target = BASE_PATH . '/storage/media';
        if (!is_dir($source)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relativePath = trim(str_replace('\\', '/', substr($file->getPathname(), strlen($source))), '/');
            if ($relativePath === '' || basename($relativePath) === '.gitkeep') {
                continue;
            }

            $targetPath = $target . '/' . $relativePath;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if (!is_file($targetPath)) {
                @rename($file->getPathname(), $targetPath);
            }
        }
    }

    private function newPublicId(PDO $connection): string
    {
        $statement = $connection->prepare('SELECT COUNT(*) FROM media WHERE public_id = :public_id');

        do {
            $publicId = bin2hex(random_bytes(12));
            $statement->execute(['public_id' => $publicId]);
        } while ((int) $statement->fetchColumn() > 0);

        return $publicId;
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
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $connection->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $statement->execute(['column' => $column]);
        return (bool) $statement->fetchColumn();
    }

    private function indexExists(PDO $connection, string $table, string $index): bool
    {
        $driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $statement = $connection->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :index");
            $statement->execute(['table' => $table, 'index' => $index]);
            return (bool) $statement->fetchColumn();
        }

        $statement = $connection->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = :index');
        $statement->execute(['index' => $index]);
        return (bool) $statement->fetchColumn();
    }
}

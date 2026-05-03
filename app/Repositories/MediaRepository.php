<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use PDO;
use function NixPHP\Database\database;

final class MediaRepository
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 120): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        try {
            $statement = $connection->prepare(<<<SQL
                SELECT *
                FROM media
                ORDER BY created_at DESC, id DESC
                LIMIT :limit
            SQL);
            $statement->bindValue('limit', max(1, min(500, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn(array $row): array => $this->hydrateRow($row), is_array($rows) ? $rows : []);
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
            $statement = $connection->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPublicId(string $publicId): ?array
    {
        $connection = $this->connection();
        $publicId = $this->normalizePublicId($publicId);
        if (!$connection instanceof PDO || $publicId === '') {
            return null;
        }

        try {
            $statement = $connection->prepare('SELECT * FROM media WHERE public_id = :public_id LIMIT 1');
            $statement->execute(['public_id' => $publicId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('No database connection available.');
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $params = [
            'public_id' => $this->normalizePublicId((string) ($payload['public_id'] ?? '')),
            'disk' => (string) ($payload['disk'] ?? 'public'),
            'visibility' => $this->normalizeVisibility((string) ($payload['visibility'] ?? 'public')),
            'original_name' => (string) ($payload['original_name'] ?? ''),
            'stored_name' => (string) ($payload['stored_name'] ?? ''),
            'storage_path' => (string) ($payload['storage_path'] ?? ''),
            'file_path' => (string) ($payload['file_path'] ?? ''),
            'public_url' => (string) ($payload['public_url'] ?? ''),
            'mime_type' => (string) ($payload['mime_type'] ?? ''),
            'extension' => (string) ($payload['extension'] ?? ''),
            'size_bytes' => (int) ($payload['size_bytes'] ?? 0),
            'width' => $payload['width'] ?? null,
            'height' => $payload['height'] ?? null,
            'alt_text' => $this->nullableText($payload['alt_text'] ?? null, 255),
            'uploaded_by' => ((int) ($payload['uploaded_by'] ?? 0)) > 0 ? (int) $payload['uploaded_by'] : null,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        if ($params['public_id'] === '') {
            throw new \InvalidArgumentException('Media public id cannot be empty.');
        }

        $statement = $connection->prepare(<<<SQL
            INSERT INTO media (
                public_id,
                disk,
                visibility,
                original_name,
                stored_name,
                storage_path,
                file_path,
                public_url,
                mime_type,
                extension,
                size_bytes,
                width,
                height,
                alt_text,
                uploaded_by,
                metadata_json,
                created_at,
                updated_at
            ) VALUES (
                :public_id,
                :disk,
                :visibility,
                :original_name,
                :stored_name,
                :storage_path,
                :file_path,
                :public_url,
                :mime_type,
                :extension,
                :size_bytes,
                :width,
                :height,
                :alt_text,
                :uploaded_by,
                :metadata_json,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        SQL);
        $statement->execute($params);

        $created = $this->find((int) $connection->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('Stored media could not be loaded.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return null;
        }

        $current = $this->find($id);
        if (!is_array($current)) {
            return null;
        }

        $visibility = array_key_exists('visibility', $payload)
            ? $this->normalizeVisibility((string) $payload['visibility'])
            : (string) ($current['visibility'] ?? 'public');
        $altText = array_key_exists('alt_text', $payload)
            ? $this->nullableText($payload['alt_text'] ?? null, 255)
            : $this->nullableText($current['alt_text'] ?? null, 255);

        $statement = $connection->prepare(<<<SQL
            UPDATE media
            SET visibility = :visibility,
                alt_text = :alt_text,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        SQL);
        $statement->execute([
            'id' => $id,
            'visibility' => $visibility,
            'alt_text' => $altText,
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $id <= 0) {
            return false;
        }

        $statement = $connection->prepare('DELETE FROM media WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return array{total:int,images:int,documents:int,bytes:int}
     */
    public function summary(): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return ['total' => 0, 'images' => 0, 'documents' => 0, 'bytes' => 0];
        }

        try {
            $row = $connection->query(<<<SQL
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN mime_type LIKE 'image/%' THEN 1 ELSE 0 END) AS images,
                    SUM(CASE WHEN mime_type NOT LIKE 'image/%' THEN 1 ELSE 0 END) AS documents,
                    COALESCE(SUM(size_bytes), 0) AS bytes
                FROM media
            SQL)->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return ['total' => 0, 'images' => 0, 'documents' => 0, 'bytes' => 0];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'images' => (int) ($row['images'] ?? 0),
            'documents' => (int) ($row['documents'] ?? 0),
            'bytes' => (int) ($row['bytes'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        $size = (int) ($row['size_bytes'] ?? 0);
        $width = isset($row['width']) ? (int) $row['width'] : null;
        $height = isset($row['height']) ? (int) $row['height'] : null;
        $width = $width !== 0 ? $width : null;
        $height = $height !== 0 ? $height : null;

        return [
            ...$row,
            'id' => (int) ($row['id'] ?? 0),
            'visibility' => $this->normalizeVisibility((string) ($row['visibility'] ?? 'public')),
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
            'metadata' => $this->decodeJson((string) ($row['metadata_json'] ?? '{}')),
            'kind' => $this->kindFromMime((string) ($row['mime_type'] ?? '')),
            'size_label' => $this->formatBytes($size),
            'dimensions_label' => $width !== null && $height !== null ? $width . ' x ' . $height : '',
        ];
    }

    private function kindFromMime(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType === 'application/pdf' || str_starts_with($mimeType, 'text/')) {
            return 'document';
        }

        if (str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'presentation') || str_contains($mimeType, 'wordprocessing')) {
            return 'document';
        }

        if ($mimeType === 'application/zip') {
            return 'archive';
        }

        return 'file';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return number_format($value, $value >= 10 ? 1 : 2) . ' ' . $unit;
            }
            $value /= 1024;
        }

        return $bytes . ' B';
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) : $text;
    }

    private function normalizePublicId(string $publicId): string
    {
        $publicId = strtolower(trim($publicId));
        return preg_match('/^[a-z0-9]{16,40}$/', $publicId) === 1 ? $publicId : '';
    }

    private function normalizeVisibility(string $visibility): string
    {
        $visibility = strtolower(trim($visibility));
        if ($visibility === 'admin') {
            return 'registered';
        }

        return in_array($visibility, ['public', 'registered', 'private'], true) ? $visibility : 'public';
    }

    private function decodeJson(string $json): array
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

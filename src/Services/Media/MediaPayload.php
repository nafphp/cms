<?php

declare(strict_types=1);

namespace src\Services\Media;

use src\Models\Media;

final class MediaPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromEntity(Media $media): array
    {
        $row = [];
        $reflection = new \ReflectionClass($media);

        do {
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
                $row[$property->getName()] = $property->getValue($media);
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof \ReflectionClass);

        return self::fromRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function fromRow(array $row): array
    {
        $size = (int) ($row['size_bytes'] ?? 0);
        $width = isset($row['width']) ? (int) $row['width'] : null;
        $height = isset($row['height']) ? (int) $row['height'] : null;
        $width = $width !== 0 ? $width : null;
        $height = $height !== 0 ? $height : null;

        return [
            ...$row,
            'id' => (int) ($row['id'] ?? 0),
            'visibility' => self::normalizeVisibility((string) ($row['visibility'] ?? 'public')),
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
            'metadata' => self::decodeJson((string) ($row['metadata_json'] ?? '{}')),
            'kind' => self::kindFromMime((string) ($row['mime_type'] ?? '')),
            'size_label' => self::formatBytes($size),
            'dimensions_label' => $width !== null && $height !== null ? $width . ' x ' . $height : '',
        ];
    }

    public static function kindFromMime(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType === 'application/pdf' || str_starts_with($mimeType, 'text/')) {
            return 'document';
        }

        if (
            str_contains($mimeType, 'spreadsheet')
            || str_contains($mimeType, 'presentation')
            || str_contains($mimeType, 'wordprocessing')
        ) {
            return 'document';
        }

        if ($mimeType === 'application/zip') {
            return 'archive';
        }

        return 'file';
    }

    public static function normalizeVisibility(string $visibility): string
    {
        $visibility = strtolower(trim($visibility));
        if ($visibility === 'admin') {
            return 'registered';
        }

        return in_array($visibility, ['public', 'registered', 'private'], true) ? $visibility : 'public';
    }

    public static function formatBytes(int $bytes): string
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

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use src\Api\MediaApi;
use src\Mcp\Tools\MediaSummaryTool;
use src\Models\Media;
use src\Services\Media\MediaStorageService;
use src\Storage\EntityStorage;
use Tests\Support\DatabaseTestCase;

final class MediaSummaryToolTest extends DatabaseTestCase
{
    public function testReturnsMediaSummaryAndRecentItems(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id TEXT NOT NULL,
                disk TEXT NOT NULL,
                visibility TEXT NOT NULL,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                storage_path TEXT NOT NULL,
                file_path TEXT NOT NULL,
                public_url TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                extension TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                width INTEGER NULL,
                height INTEGER NULL,
                alt_text TEXT NULL,
                uploaded_by INTEGER NULL,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )
        SQL);

        $this->insertMedia([
            'public_id' => 'aaaaaaaaaaaaaaaaaaaaaaaa',
            'original_name' => 'Document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 2048,
            'created_at' => '2026-05-01 10:00:00',
        ]);
        $this->insertMedia([
            'public_id' => 'bbbbbbbbbbbbbbbbbbbbbbbb',
            'original_name' => 'Image.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 512,
            'width' => 320,
            'height' => 240,
            'created_at' => '2026-05-02 10:00:00',
        ]);

        $storage = new EntityStorage(Media::class, $this->pdo);
        $api = new MediaApi(new MediaStorageService($storage, sys_get_temp_dir(), '/media'), $storage);
        $result = (new MediaSummaryTool($api))->handle(['include_recent' => true, 'limit' => 1]);

        self::assertTrue($result['database_available']);
        self::assertSame(2, $result['total']);
        self::assertSame(1, $result['images']);
        self::assertSame(1, $result['documents']);
        self::assertSame(2560, $result['bytes']);
        self::assertSame(['image' => 1, 'document' => 1, 'archive' => 0, 'file' => 0], $result['kind_counts']);
        self::assertCount(1, $result['recent']);
        self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbb', $result['recent'][0]['public_id']);
        self::assertSame('Image.png', $result['recent'][0]['original_name']);
        self::assertSame('320 x 240', $result['recent'][0]['dimensions_label']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function insertMedia(array $overrides): int
    {
        return $this->insertRow('media', array_replace([
            'public_id' => 'dddddddddddddddddddddddd',
            'disk' => 'public',
            'visibility' => 'public',
            'original_name' => 'File.bin',
            'stored_name' => 'file.bin',
            'storage_path' => 'media/file.bin',
            'file_path' => '/tmp/file.bin',
            'public_url' => '/media/file.bin',
            'mime_type' => 'application/octet-stream',
            'extension' => 'bin',
            'size_bytes' => 1,
            'width' => null,
            'height' => null,
            'alt_text' => null,
            'uploaded_by' => null,
            'metadata_json' => '{}',
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => null,
        ], $overrides));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use NixCMS\Api\MediaApi;
use NixCMS\Models\Media;
use NixCMS\Services\Media\MediaStorageService;
use NixCMS\Storage\EntityStorage;
use Tests\Support\DatabaseTestCase;

final class MediaApiTest extends DatabaseTestCase
{
    private MediaApi $api;
    private EntityStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->storage = new EntityStorage(Media::class, $this->pdo);
        $this->api = new MediaApi(
            new MediaStorageService($this->storage, sys_get_temp_dir(), '/media'),
            $this->storage,
        );
    }

    public function testRecentSummaryAndLookupUseStorage(): void
    {
        $this->insertMedia([
            'public_id' => 'aaaaaaaaaaaaaaaaaaaaaaaa',
            'original_name' => 'Old document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 2048,
            'created_at' => '2026-05-01 10:00:00',
        ]);
        $this->insertMedia([
            'public_id' => 'bbbbbbbbbbbbbbbbbbbbbbbb',
            'original_name' => 'Fresh image.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 512,
            'width' => 320,
            'height' => 240,
            'metadata_json' => '{"collection":"dashboard"}',
            'created_at' => '2026-05-02 10:00:00',
        ]);
        $this->insertMedia([
            'public_id' => 'eeeeeeeeeeeeeeeeeeeeeeee',
            'original_name' => 'Archive.zip',
            'mime_type' => 'application/zip',
            'extension' => 'zip',
            'size_bytes' => 4096,
            'created_at' => '2026-04-30 10:00:00',
        ]);
        $this->insertMedia([
            'public_id' => 'ffffffffffffffffffffffff',
            'original_name' => 'Payload.bin',
            'mime_type' => 'application/octet-stream',
            'extension' => 'bin',
            'size_bytes' => 128,
            'created_at' => '2026-04-29 10:00:00',
        ]);

        $recent = $this->api->recent(2);
        $summary = $this->api->summary();
        $found = $this->api->findByPublicId('bbbbbbbbbbbbbbbbbbbbbbbb');

        self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbb', $recent[0]['public_id']);
        self::assertSame('image', $recent[0]['kind']);
        self::assertSame('320 x 240', $recent[0]['dimensions_label']);
        self::assertSame(['collection' => 'dashboard'], $recent[0]['metadata']);
        self::assertSame(4, $summary['total']);
        self::assertSame(1, $summary['images']);
        self::assertSame(1, $summary['documents']);
        self::assertSame(1, $summary['archives']);
        self::assertSame(1, $summary['files']);
        self::assertSame(6784, $summary['bytes']);
        self::assertSame(['image' => 1, 'document' => 1, 'archive' => 1, 'file' => 1], $summary['kind_counts']);
        self::assertSame('Fresh image.png', $found['original_name'] ?? null);
    }

    public function testUpdateUsesStorageAndKeepsExistingAltTextByDefault(): void
    {
        $id = $this->insertMedia([
            'public_id' => 'cccccccccccccccccccccccc',
            'original_name' => 'Preview.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'alt_text' => 'Preview',
            'created_at' => '2026-05-03 10:00:00',
        ]);

        $updated = $this->api->update($id, ['visibility' => 'private']);

        self::assertSame('private', $updated['visibility'] ?? null);
        self::assertSame('Preview', $updated['alt_text'] ?? null);
        self::assertSame('private', $this->fetchOne('SELECT visibility FROM media WHERE id = :id', ['id' => $id])['visibility'] ?? null);
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

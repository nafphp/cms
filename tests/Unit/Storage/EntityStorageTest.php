<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Models\Media;
use NixCMS\Storage\Criteria;
use NixCMS\Storage\EntityStorage;
use Tests\Support\DatabaseTestCase;

final class EntityStorageTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(<<<SQL
            CREATE TABLE media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                disk TEXT NULL,
                public_id TEXT NULL,
                visibility TEXT NULL,
                original_name TEXT NULL,
                stored_name TEXT NULL,
                storage_path TEXT NULL,
                file_path TEXT NULL,
                public_url TEXT NULL,
                mime_type TEXT NULL,
                extension TEXT NULL,
                size_bytes INTEGER NULL,
                width INTEGER NULL,
                height INTEGER NULL,
                alt_text TEXT NULL,
                uploaded_by INTEGER NULL,
                metadata_json TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )
        SQL);

        $this->insertRow('media', [
            'disk' => 'public',
            'public_id' => 'hero',
            'visibility' => 'public',
            'original_name' => 'Hero Image.jpg',
            'stored_name' => 'hero.jpg',
            'storage_path' => 'media/hero.jpg',
            'file_path' => '/tmp/hero.jpg',
            'public_url' => '/media/hero.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 900,
            'width' => 1200,
            'height' => 800,
            'alt_text' => 'NixCMS hero',
            'uploaded_by' => 1,
            'metadata_json' => '{}',
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => '2026-05-01 10:00:00',
        ]);
        $this->insertRow('media', [
            'disk' => 'public',
            'public_id' => 'brief',
            'visibility' => 'private',
            'original_name' => 'Editorial Brief.pdf',
            'stored_name' => 'brief.pdf',
            'storage_path' => 'media/brief.pdf',
            'file_path' => '/tmp/brief.pdf',
            'public_url' => '/media/brief.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 400,
            'width' => null,
            'height' => null,
            'alt_text' => null,
            'uploaded_by' => 2,
            'metadata_json' => '{}',
            'created_at' => '2026-05-02 10:00:00',
            'updated_at' => '2026-05-02 10:00:00',
        ]);
        $this->insertRow('media', [
            'disk' => 'public',
            'public_id' => 'thumb',
            'visibility' => 'public',
            'original_name' => 'Thumbnail.png',
            'stored_name' => 'thumb.png',
            'storage_path' => 'media/thumb.png',
            'file_path' => '/tmp/thumb.png',
            'public_url' => '/media/thumb.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 120,
            'width' => 320,
            'height' => 240,
            'alt_text' => 'Small preview',
            'uploaded_by' => 1,
            'metadata_json' => '{}',
            'created_at' => '2026-05-03 10:00:00',
            'updated_at' => '2026-05-03 10:00:00',
        ]);
    }

    public function testSearchAppliesCriteriaFiltersSortingAndLimit(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);

        $media = $storage->search(
            Criteria::create()
                ->equals('visibility', 'public')
                ->in('extension', ['jpg', 'png'])
                ->gte('size_bytes', 100)
                ->contains('original_name', 'h')
                ->sort('size_bytes', 'DESC')
                ->limit(1),
        );

        self::assertCount(1, $media);
        self::assertInstanceOf(Media::class, $media[0]);
        self::assertSame('/media/hero.jpg', $media[0]->getPublicUrl());
    }

    public function testFirstAndCountUseCriteria(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);
        $criteria = Criteria::create()
            ->equals('visibility', 'public')
            ->sort('created_at', 'DESC');

        $first = $storage->first($criteria);

        self::assertInstanceOf(Media::class, $first);
        self::assertSame('/media/thumb.png', $first->getPublicUrl());
        self::assertNull($criteria->limitValue());
        self::assertSame(2, $storage->count(Criteria::create()->equals('visibility', 'public')));
    }

    public function testNullFiltersUseSqlNullSemantics(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);

        $missingAltText = $storage->search(Criteria::create()->equals('alt_text', null));
        $presentAltText = $storage->search(Criteria::create()->notEquals('alt_text', null));

        self::assertCount(1, $missingAltText);
        self::assertCount(2, $presentAltText);
    }

    public function testCreatePersistsEntityThroughOrmEntityManager(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);

        $media = $storage->create([
            'disk' => 'public',
            'public_id' => 'created',
            'visibility' => 'public',
            'original_name' => 'Created.png',
            'stored_name' => 'created.png',
            'storage_path' => 'media/created.png',
            'file_path' => '/tmp/created.png',
            'public_url' => '/media/created.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 42,
            'width' => 10,
            'height' => 20,
            'alt_text' => 'Created alt',
            'uploaded_by' => 3,
            'metadata_json' => '{}',
            'created_at' => '2026-05-04 10:00:00',
            'updated_at' => '2026-05-04 10:00:00',
        ]);

        self::assertInstanceOf(Media::class, $media);
        self::assertIsInt($media->getId());
        self::assertSame('/media/created.png', $storage->find((int) $media->getId())?->getPublicUrl());
    }

    public function testUpdateAppliesPartialDataAndSavesThroughOrmEntityManager(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);

        $updated = $storage->update(1, [
            'visibility' => 'private',
            'alt_text' => 'Updated alt',
        ]);

        self::assertInstanceOf(Media::class, $updated);
        self::assertSame('Updated alt', $updated->getAltText());
        self::assertSame('private', $this->fetchOne('SELECT visibility FROM media WHERE id = 1')['visibility'] ?? null);
    }

    public function testDeleteRemovesEntityById(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);

        self::assertTrue($storage->delete(1));
        self::assertNull($storage->find(1));
    }

    public function testCriteriaNormalizesBackedEnums(): void
    {
        $criteria = Criteria::create()->equals('status', ArticleStatus::PUBLISHED);

        self::assertSame(ArticleStatus::PUBLISHED->value, $criteria->filters()[0]->value);
    }

    public function testUnknownColumnsAreRejected(): void
    {
        $storage = new EntityStorage(Media::class, $this->pdo);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Column not allowed for ' . Media::class . ': does_not_exist');

        $storage->search(Criteria::create()->equals('does_not_exist', 'x'));
    }
}

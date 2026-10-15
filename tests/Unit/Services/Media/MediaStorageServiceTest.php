<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Media;

use FilesystemIterator;
use InvalidArgumentException;
use Naf\CMS\Models\Media;
use Naf\CMS\Services\Media\MediaStorageService;
use Naf\CMS\Storage\EntityStorage;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\DatabaseTestCase;

final class MediaStorageServiceTest extends DatabaseTestCase
{
    public function testConfigDefaultsMatchServiceFallback(): void
    {
        $config = require dirname(__DIR__, 4) . '/src/config.php';

        self::assertSame(
            array_keys($config['cms']['media']['allowed_extensions']),
            array_keys(MediaStorageService::defaultAllowedExtensions()),
        );
    }

    private string $publicRoot;
    /** @var EntityStorage<Media> */
    private EntityStorage $storage;
    private MediaStorageService $service;

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
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NULL
            )
        SQL);

        $this->publicRoot = sys_get_temp_dir() . '/nafcms-media-test-' . bin2hex(random_bytes(6));
        mkdir($this->publicRoot, 0755, true);

        $this->storage = new EntityStorage(Media::class, $this->pdo);
        $this->service = new MediaStorageService(
            $this->storage,
            $this->publicRoot,
            '/media',
            1024 * 1024,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->publicRoot);

        parent::tearDown();
    }

    public function testStoresUploadedImageAndMetadata(): void
    {
        $media = $this->service->storeUploadedFile(
            $this->uploadedFile($this->tinyPng(), 'Hero Image.png', 'image/png'),
            uploadedBy: 7,
            context: ['collection' => 'articles', 'alt_text' => 'Hero image', 'visibility' => 'registered'],
        );

        self::assertSame('Hero Image.png', $media['original_name']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $media['public_id']);
        self::assertSame('registered', $media['visibility']);
        self::assertSame('image/png', $media['mime_type']);
        self::assertSame('png', $media['extension']);
        self::assertSame('/media/' . $media['public_id'] . '/' . $media['stored_name'], $media['public_url']);
        self::assertSame($media['storage_path'], $media['file_path']);
        self::assertSame('Hero image', $media['alt_text']);
        self::assertSame(7, $media['uploaded_by']);
        self::assertSame(1, $media['width']);
        self::assertSame(1, $media['height']);
        self::assertFileExists($this->publicRoot . '/' . $media['storage_path']);
        self::assertSame(realpath($this->publicRoot . '/' . $media['storage_path']), $this->service->pathFor($media));
        self::assertSame($media['id'], $this->storage->find((int) $media['id'])?->getId());

        $row = $this->fetchOne('SELECT * FROM media WHERE id = :id', ['id' => $media['id']]);
        self::assertIsArray($row);
        self::assertSame($media['public_url'], $row['public_url']);
    }

    public function testRejectsDisallowedExtensionBeforeWritingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('extension');

        try {
            $this->service->storeUploadedFile(
                $this->uploadedFile('<?php echo "no";', 'shell.php', 'application/x-php'),
            );
        } finally {
            self::assertSame([], $this->storage->search());
            self::assertSame([], $this->storedFiles());
        }
    }

    public function testClientMimeCannotOverrideAnIncompatibleDetectedType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service->storeUploadedFile(
                $this->uploadedFile('<?php echo "disposable";', 'disguised.png', 'image/png'),
            );
        } finally {
            self::assertSame([], $this->storage->search());
            self::assertSame([], $this->storedFiles());
        }
    }

    public function testDeleteRemovesMetadataAndStoredFile(): void
    {
        $media = $this->service->storeUploadedFile(
            $this->uploadedFile($this->tinyPng(), 'preview.png', 'image/png'),
        );
        $path = $this->publicRoot . '/' . $media['storage_path'];

        self::assertFileExists($path);
        self::assertTrue($this->service->delete((int) $media['id']));
        self::assertFileDoesNotExist($path);
        self::assertNull($this->storage->find((int) $media['id']));
    }

    private function uploadedFile(string $contents, string $filename, string $mimeType): UploadedFile
    {
        return new UploadedFile(
            Stream::create($contents),
            strlen($contents),
            UPLOAD_ERR_OK,
            $filename,
            $mimeType,
        );
    }

    private function tinyPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true,
        );
    }

    /**
     * @return array<int, string>
     */
    private function storedFiles(): array
    {
        if (!is_dir($this->publicRoot)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->publicRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}

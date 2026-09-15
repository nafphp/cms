<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Media;

use finfo;
use InvalidArgumentException;
use Naf\CMS\Contracts\MediaStorageInterface;
use Naf\CMS\Models\Media;
use Naf\CMS\Storage\EntityStorage;
use Naf\CMS\Support\Slug;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

use function Naf\config;
use function Naf\I18n\t;
use function Naf\log;

final class MediaStorageService implements MediaStorageInterface
{
    private const int DEFAULT_MAX_UPLOAD_SIZE = 25 * 1024 * 1024;

    /**
     * @var array<string, array<int, string>>
     */
    private const array DEFAULT_ALLOWED_EXTENSIONS = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    ];

    /** @return array<string, array<int, string>> */
    public static function defaultAllowedExtensions(): array
    {
        return self::DEFAULT_ALLOWED_EXTENSIONS;
    }

    public function __construct(
        private EntityStorage $storage,
        private ?string $storageRoot = null,
        private ?string $publicUrlPrefix = null,
        private ?int $maxUploadSize = null,
        private ?array $allowedExtensions = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function storeUploadedFile(UploadedFileInterface $file, ?int $uploadedBy = null, array $context = []): array
    {
        $this->assertUploadIsUsable($file);

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0) {
            throw new InvalidArgumentException(t('Uploaded file is empty.'));
        }

        if ($size > $this->maxUploadSize()) {
            throw new InvalidArgumentException(sprintf(
                t('Uploaded file is larger than %s.'),
                $this->formatBytes($this->maxUploadSize()),
            ));
        }

        $originalName = $this->normalizeOriginalName((string) ($file->getClientFilename() ?? 'upload'));
        $extension    = $this->extensionFromName($originalName);
        if ($extension === '' || !array_key_exists($extension, $this->allowedExtensions())) {
            throw new InvalidArgumentException(t('This file extension is not allowed.'));
        }

        $detectedMime = $this->detectMimeType($file);
        $clientMime   = strtolower(trim((string) ($file->getClientMediaType() ?? '')));
        $mimeType     = $this->resolveMimeType($extension, $detectedMime, $clientMime);

        $storageDirectory  = $this->storageDirectory($context);
        $absoluteDirectory = $this->storageRoot() . '/' . $storageDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException(t('Media directory could not be created.'));
        }

        $storedName     = $this->storedName($originalName, $extension, $absoluteDirectory);
        $absoluteTarget = $absoluteDirectory . '/' . $storedName;

        try {
            $file->moveTo($absoluteTarget);
        } catch (Throwable $exception) {
            throw new RuntimeException(t('Uploaded file could not be stored.'), previous: $exception);
        }

        $publicId         = $this->publicId();
        [$width, $height] = $this->imageSize($absoluteTarget, $mimeType);
        $storagePath      = $storageDirectory . '/' . $storedName;
        $filePath         = $this->relativeFilePath($storagePath);
        $publicUrl        = $this->publicUrlPrefix() . '/' . $publicId . '/' . rawurlencode($storedName);
        $createdAt        = date('Y-m-d H:i:s');

        try {
            /** @var Media $media */
            $media = $this->storage->create([
                'public_id'     => $publicId,
                'disk'          => 'public',
                'visibility'    => $this->visibility($context),
                'original_name' => $originalName,
                'stored_name'   => $storedName,
                'storage_path'  => $storagePath,
                'file_path'     => $filePath,
                'public_url'    => $publicUrl,
                'mime_type'     => $mimeType,
                'extension'     => $extension,
                'size_bytes'    => $size,
                'width'         => $width,
                'height'        => $height,
                'alt_text'      => $this->nullableText($context['alt_text'] ?? null, 255),
                'uploaded_by'   => $uploadedBy,
                'metadata_json' => json_encode([
                    'collection'         => $this->collection($context),
                    'client_mime_type'   => $clientMime,
                    'detected_mime_type' => $detectedMime,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            return MediaPayload::fromEntity($media);
        } catch (Throwable $exception) {
            if (is_file($absoluteTarget)) {
                @unlink($absoluteTarget);
            }

            throw $exception;
        }
    }

    public function delete(int $id): bool
    {
        $media = $this->storage->find($id);
        if (!$media instanceof Media) {
            return false;
        }

        $payload        = MediaPayload::fromEntity($media);
        $fullPath       = $this->absolutePath((string) ($payload['storage_path'] ?? ''));
        $quarantinePath = null;
        if ($fullPath !== null && is_file($fullPath)) {
            $quarantinePath = $fullPath . '.deleting-' . bin2hex(random_bytes(6));
            if (!rename($fullPath, $quarantinePath)) {
                log()->error('MediaStorageService.delete could not quarantine media file.', [
                    'media_id' => $id,
                    'path'     => $fullPath,
                ]);

                return false;
            }
        }

        try {
            $deleted = $this->storage->delete($id);
        } catch (Throwable $exception) {
            $this->restoreQuarantinedFile($quarantinePath, $fullPath, $id);
            throw $exception;
        }

        if (!$deleted) {
            $this->restoreQuarantinedFile($quarantinePath, $fullPath, $id);

            return false;
        }

        if ($quarantinePath !== null && is_file($quarantinePath) && !unlink($quarantinePath)) {
            log()->warning('MediaStorageService.delete left a quarantined file for cleanup.', [
                'media_id' => $id,
                'path'     => $quarantinePath,
            ]);
        }

        return true;
    }

    private function restoreQuarantinedFile(?string $quarantinePath, ?string $fullPath, int $mediaId): void
    {
        if ($quarantinePath === null || $fullPath === null || !is_file($quarantinePath)) {
            return;
        }

        if (!rename($quarantinePath, $fullPath)) {
            log()->error('MediaStorageService.delete could not restore quarantined media file.', [
                'media_id' => $mediaId,
                'path'     => $quarantinePath,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $media
     */
    public function pathFor(array $media): ?string
    {
        return $this->absolutePath((string) ($media['storage_path'] ?? ''));
    }

    /**
     * @return array<int, string>
     */
    public function acceptedExtensions(): array
    {
        return array_keys($this->allowedExtensions());
    }

    public function maxUploadSize(): int
    {
        if ($this->maxUploadSize !== null) {
            return $this->maxUploadSize;
        }

        try {
            return (int) config('cms:media:max_upload_size', self::DEFAULT_MAX_UPLOAD_SIZE);
        } catch (Throwable $e) {
            log()->warning(sprintf('MediaStorageService.maxUploadSize failed: %s', $e->getMessage()), ['exception' => $e]);

            return self::DEFAULT_MAX_UPLOAD_SIZE;
        }
    }

    private function assertUploadIsUsable(UploadedFileInterface $file): void
    {
        $error = $file->getError();
        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new InvalidArgumentException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('Uploaded file is too large.'),
            UPLOAD_ERR_PARTIAL                        => t('Uploaded file was only partially received.'),
            UPLOAD_ERR_NO_FILE                        => t('No file was uploaded.'),
            default                                   => t('Upload failed.'),
        });
    }

    private function detectMimeType(UploadedFileInterface $file): string
    {
        try {
            $stream = $file->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $sample = $stream->read(8192);
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
        } catch (Throwable $e) {
            log()->warning(sprintf('MediaStorageService.detectMimeType failed: %s', $e->getMessage()), ['exception' => $e]);
            $sample = '';
        }

        if ($sample === '' || !class_exists(finfo::class)) {
            return '';
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($sample);

        return is_string($mimeType) ? strtolower(trim($mimeType)) : '';
    }

    private function resolveMimeType(string $extension, string $detectedMime, string $clientMime): string
    {
        $allowed = $this->allowedExtensions()[$extension] ?? [];
        foreach ([$detectedMime, $clientMime] as $candidate) {
            if ($candidate !== '' && in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException(t('This file type is not allowed.'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function allowedExtensions(): array
    {
        if ($this->allowedExtensions !== null) {
            $configured = $this->allowedExtensions;
        } else {
            try {
                $configured = config('cms:media:allowed_extensions', []);
            } catch (Throwable $e) {
                log()->warning(sprintf('MediaStorageService.allowedExtensions failed: %s', $e->getMessage()), ['exception' => $e]);
                $configured = [];
            }
        }
        if (!is_array($configured) || $configured === []) {
            return self::DEFAULT_ALLOWED_EXTENSIONS;
        }

        $normalized = [];
        foreach ($configured as $extension => $mimes) {
            $extension = strtolower(trim((string) $extension, " \t\n\r\0\x0B."));
            if ($extension === '') {
                continue;
            }
            $normalized[$extension] = array_values(array_filter(array_map(
                static fn(mixed $mime): string => strtolower(trim((string) $mime)),
                is_array($mimes) ? $mimes : [$mimes],
            )));
        }

        return $normalized !== [] ? $normalized : self::DEFAULT_ALLOWED_EXTENSIONS;
    }

    private function storageRoot(): string
    {
        if ($this->storageRoot !== null) {
            return rtrim($this->storageRoot, '/\\');
        }

        try {
            $root = (string) config('cms:media:storage_path', config('cms:media:public_path', BASE_PATH . '/storage/media'));
        } catch (Throwable $e) {
            log()->warning(sprintf('MediaStorageService.storageRoot failed: %s', $e->getMessage()), ['exception' => $e]);
            $root = BASE_PATH . '/storage/media';
        }

        return rtrim($root, '/\\');
    }

    private function publicUrlPrefix(): string
    {
        if ($this->publicUrlPrefix !== null) {
            $prefix = $this->publicUrlPrefix;
        } else {
            try {
                $prefix = (string) config('cms:media:public_url', '/media');
            } catch (Throwable $e) {
                log()->warning(sprintf('MediaStorageService.publicUrlPrefix failed: %s', $e->getMessage()), ['exception' => $e]);
                $prefix = '/media';
            }
        }

        $prefix = '/' . trim($prefix, '/');

        return $prefix === '/' ? '/media' : $prefix;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function storageDirectory(array $context): string
    {
        $collection = $this->collection($context);
        $datedPath  = date('Y/m');

        return $collection !== '' ? $collection . '/' . $datedPath : $datedPath;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function collection(array $context): string
    {
        $collection = Slug::fromString((string) ($context['collection'] ?? ''));

        return trim($collection, '/');
    }

    private function storedName(string $originalName, string $extension, string $absoluteDirectory): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $slug     = Slug::fromString($baseName) ?: 'file';
        $slug     = substr($slug, 0, 72);

        do {
            $name = $slug . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        } while (is_file($absoluteDirectory . '/' . $name));

        return $name;
    }

    private function publicId(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function visibility(array $context): string
    {
        return MediaPayload::normalizeVisibility((string) ($context['visibility'] ?? 'public'));
    }

    private function relativeFilePath(string $storagePath): string
    {
        $root = rtrim(str_replace('\\', '/', $this->storageRoot()), '/');
        $base = rtrim(str_replace('\\', '/', BASE_PATH), '/');
        if (str_starts_with($root, $base . '/')) {
            return trim(substr($root, strlen($base) + 1), '/') . '/' . $storagePath;
        }

        return $storagePath;
    }

    private function normalizeOriginalName(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));

        return $name !== '' ? substr($name, 0, 255) : 'upload';
    }

    private function extensionFromName(string $name): string
    {
        return strtolower(trim(pathinfo($name, PATHINFO_EXTENSION)));
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    private function imageSize(string $path, string $mimeType): array
    {
        if (!str_starts_with($mimeType, 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($path);
        if (!is_array($size)) {
            return [null, null];
        }

        return [(int) ($size[0] ?? 0) ?: null, (int) ($size[1] ?? 0) ?: null];
    }

    private function absolutePath(string $storagePath): ?string
    {
        $storagePath = trim(str_replace('\\', '/', $storagePath), '/');
        if ($storagePath === '' || str_contains($storagePath, '..')) {
            return null;
        }

        $path     = $this->storageRoot() . '/' . $storagePath;
        $root     = realpath($this->storageRoot());
        $realPath = realpath($path);
        if (!is_string($root) || !is_string($realPath)) {
            return null;
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($realPath, $root) ? $realPath : null;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) : $text;
    }
}

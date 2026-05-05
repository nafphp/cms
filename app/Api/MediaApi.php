<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Models\Media;
use NixCMS\Services\Media\MediaPayload;
use NixCMS\Services\Media\MediaStorageService;
use NixCMS\Storage\Criteria;
use NixCMS\Storage\EntityStorage;
use NixCMS\Support\AuditEntry;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Domain API for media. Exposes media-specific behaviour, delegates DB queries to Storage, and file I/O to MediaStorageService.
 *
 * Data access path: ContentApi → Storage<Media> → db()
 * Layer 3 (Repository) is intentionally skipped — Storage covers all queries; there is no MediaRepository.
 * Use ->storage() for Criteria-based reads.
 */
final class MediaApi
{
    public function __construct(
        private MediaStorageService $storage,
        private ?EntityStorage $mediaStorage = null,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 120): array
    {
        return array_map(
            static fn(Media $media): array => MediaPayload::fromEntity($media),
            $this->storage()->search(
                Criteria::create()
                    ->sort('created_at', 'DESC')
                    ->sort('id', 'DESC')
                    ->limit(max(1, min(500, $limit))),
            ),
        );
    }

    /**
     * @return array<int, Media>
     */
    public function search(?Criteria $criteria = null): array
    {
        return $this->storage()->search($criteria);
    }

    /**
     * @return array{total:int,images:int,documents:int,archives:int,files:int,bytes:int,kind_counts:array<string, int>}
     */
    public function summary(): array
    {
        $summary = [
            'total' => 0,
            'images' => 0,
            'documents' => 0,
            'archives' => 0,
            'files' => 0,
            'bytes' => 0,
            'kind_counts' => [
                'image' => 0,
                'document' => 0,
                'archive' => 0,
                'file' => 0,
            ],
        ];

        foreach ($this->storage()->search() as $media) {
            $payload = MediaPayload::fromEntity($media);
            $kind = (string) ($payload['kind'] ?? 'file');
            $kind = array_key_exists($kind, $summary['kind_counts']) ? $kind : 'file';

            $summary['total']++;
            $summary['bytes'] += (int) ($payload['size_bytes'] ?? 0);
            $summary['kind_counts'][$kind]++;

            switch ($kind) {
                case 'image':
                    $summary['images']++;
                    break;
                case 'document':
                    $summary['documents']++;
                    break;
                case 'archive':
                    $summary['archives']++;
                    break;
                default:
                    $summary['files']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $media = $this->storage()->find($id);
        return $media instanceof Media ? MediaPayload::fromEntity($media) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPublicId(string $publicId): ?array
    {
        $publicId = $this->normalizePublicId($publicId);
        if ($publicId === '') {
            return null;
        }

        $media = $this->storage()->first(Criteria::create()->equals('public_id', $publicId));
        return $media instanceof Media ? MediaPayload::fromEntity($media) : null;
    }

    public function pathFor(array $media): ?string
    {
        return $this->storage->pathFor($media);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function storeUploadedFile(UploadedFileInterface $file, ?int $uploadedBy = null, array $context = []): array
    {
        $media = $this->storage->storeUploadedFile($file, $uploadedBy, $context);

        AuditEntry::create(
            'media.uploaded',
            'media',
            (string) ($media['id'] ?? ''),
            null,
            $this->mediaAuditSnapshot($media),
            ['context' => $context],
        )->record();

        return $media;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        $old = $this->find($id);
        $mediaEntity = $this->storage()->update($id, [
            'visibility' => array_key_exists('visibility', $payload)
                ? MediaPayload::normalizeVisibility((string) $payload['visibility'])
                : (string) ($old['visibility'] ?? 'public'),
            'alt_text' => array_key_exists('alt_text', $payload)
                ? $this->nullableText($payload['alt_text'] ?? null, 255)
                : $this->nullableText($old['alt_text'] ?? null, 255),
        ]);
        $media = $mediaEntity instanceof Media ? MediaPayload::fromEntity($mediaEntity) : null;
        if ($media !== null) {
            AuditEntry::create(
                'media.updated',
                'media',
                (string) $id,
                $this->mediaAuditSnapshot($old),
                $this->mediaAuditSnapshot($media),
            )->record();
        }

        return $media;
    }

    /**
     * Persist a staged media metadata snapshot. File contents and storage paths are not stage-writable.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    public function saveStage(array $snapshot): ?array
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : $snapshot;
        $id = (int) ($snapshot['id'] ?? $fields['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Media stage requires an existing media id.');
        }

        return $this->update($id, [
            'visibility' => $fields['visibility'] ?? null,
            'alt_text' => $fields['alt_text'] ?? null,
        ]);
    }

    public function delete(int $id): bool
    {
        $old = $this->find($id);
        $deleted = $this->storage->delete($id);
        if ($deleted) {
            AuditEntry::create(
                'media.deleted',
                'media',
                (string) $id,
                $this->mediaAuditSnapshot($old),
            )->record();
        }

        return $deleted;
    }

    /**
     * @return array<int, string>
     */
    public function acceptedExtensions(): array
    {
        return $this->storage->acceptedExtensions();
    }

    public function maxUploadSize(): int
    {
        return $this->storage->maxUploadSize();
    }

    /**
     * @return EntityStorage<Media>
     */
    public function storage(): EntityStorage
    {
        return $this->mediaStorage ??= new EntityStorage(Media::class);
    }

    /**
     * @param array<string, mixed>|null $media
     * @return array<string, mixed>|null
     */
    private function mediaAuditSnapshot(?array $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'id' => (int) ($media['id'] ?? 0),
            'public_id' => (string) ($media['public_id'] ?? ''),
            'original_name' => (string) ($media['original_name'] ?? ''),
            'stored_name' => (string) ($media['stored_name'] ?? ''),
            'visibility' => (string) ($media['visibility'] ?? ''),
            'mime_type' => (string) ($media['mime_type'] ?? ''),
            'size_bytes' => (int) ($media['size_bytes'] ?? 0),
            'alt_text' => $media['alt_text'] ?? null,
            'public_url' => (string) ($media['public_url'] ?? ''),
        ];
    }

    private function normalizePublicId(string $publicId): string
    {
        $publicId = strtolower(trim($publicId));
        return preg_match('/^[a-z0-9]{16,40}$/', $publicId) === 1 ? $publicId : '';
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

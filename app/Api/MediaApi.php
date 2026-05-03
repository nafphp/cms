<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Repositories\MediaRepository;
use NixCMS\Services\Media\MediaStorageService;
use NixCMS\Support\AuditEntry;
use Psr\Http\Message\UploadedFileInterface;

final class MediaApi
{
    public function __construct(
        private MediaRepository $repository,
        private MediaStorageService $storage,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 120): array
    {
        return $this->repository->recent($limit);
    }

    /**
     * @return array{total:int,images:int,documents:int,bytes:int}
     */
    public function summary(): array
    {
        return $this->repository->summary();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPublicId(string $publicId): ?array
    {
        return $this->repository->findByPublicId($publicId);
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
        $old = $this->repository->find($id);
        $media = $this->repository->update($id, $payload);
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

    public function delete(int $id): bool
    {
        $old = $this->repository->find($id);
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
}

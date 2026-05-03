<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Repositories\MediaRepository;
use NixCMS\Services\Media\MediaStorageService;
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
        return $this->storage->storeUploadedFile($file, $uploadedBy, $context);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        return $this->repository->update($id, $payload);
    }

    public function delete(int $id): bool
    {
        return $this->storage->delete($id);
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
}

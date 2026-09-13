<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Psr\Http\Message\UploadedFileInterface;

interface MediaStorageInterface
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function storeUploadedFile(UploadedFileInterface $file, ?int $uploadedBy = null, array $context = []): array;
    public function delete(int $id): bool;
    /** @param array<string, mixed> $media */
    public function pathFor(array $media): ?string;
    /** @return array<int, string> */
    public function acceptedExtensions(): array;
    public function maxUploadSize(): int;
}

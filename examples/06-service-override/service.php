<?php

declare(strict_types=1);

namespace Naf\CMS\Examples\ServiceOverride;

use Naf\CMS\Contracts\MediaStorageInterface;
use Psr\Http\Message\UploadedFileInterface;
use function Naf\app;

final class ExampleMediaStorage implements MediaStorageInterface
{
    public function storeUploadedFile(UploadedFileInterface $file, ?int $uploadedBy = null, array $context = []): array
    {
        throw new \RuntimeException('Connect this example to the host storage provider.');
    }

    public function delete(int $id): bool
    {
        return false;
    }

    public function pathFor(array $media): ?string
    {
        return isset($media['storage_path']) ? (string) $media['storage_path'] : null;
    }

    public function acceptedExtensions(): array
    {
        return ['jpg', 'png', 'webp'];
    }

    public function maxUploadSize(): int
    {
        return 25 * 1024 * 1024;
    }
}

app()->container()->set(
    MediaStorageInterface::class,
    static fn(): MediaStorageInterface => new ExampleMediaStorage(),
);

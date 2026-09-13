<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Media;

use Naf\CMS\Api\MediaApi;
use Naf\CMS\Content\ContentTypeInterface;
use Naf\CMS\Contracts\MediaStorageInterface;
use Naf\CMS\Models\Media;
use Naf\CMS\Storage\EntityStorage;
use function Naf\app;

final class MediaContentType implements ContentTypeInterface
{
    private ?MediaApi $api = null;

    public function id(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Media';
    }

    public function modelClass(): string
    {
        return Media::class;
    }

    public function api(): MediaApi
    {
        if ($this->api === null) {
            $storage = new EntityStorage(Media::class);
            /** @var MediaStorageInterface $fileStorage */
            $fileStorage = app()->container()->get(MediaStorageInterface::class);
            $this->api = new MediaApi($fileStorage, $storage);
        }

        return $this->api;
    }
}

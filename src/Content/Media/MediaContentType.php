<?php

declare(strict_types=1);

namespace src\Content\Media;

use src\Api\MediaApi;
use src\Content\ContentTypeInterface;
use src\Models\Media;
use src\Services\Media\MediaStorageService;
use src\Storage\EntityStorage;

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
            $this->api = new MediaApi(new MediaStorageService($storage), $storage);
        }

        return $this->api;
    }
}

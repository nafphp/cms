<?php

declare(strict_types=1);

namespace NixCMS\Content\Media;

use NixCMS\Api\MediaApi;
use NixCMS\Content\ContentTypeInterface;
use NixCMS\Models\Media;
use NixCMS\Services\Media\MediaStorageService;
use NixCMS\Storage\EntityStorage;

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

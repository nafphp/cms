<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Media;

use NixPHP\CMS\Api\MediaApi;
use NixPHP\CMS\Content\ContentTypeInterface;
use NixPHP\CMS\Models\Media;
use NixPHP\CMS\Services\Media\MediaStorageService;
use NixPHP\CMS\Storage\EntityStorage;

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

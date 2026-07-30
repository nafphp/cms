<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Media;

use NixPHP\CMS\Api\MediaApi;
use NixPHP\CMS\Content\ContentTypeInterface;
use NixPHP\CMS\Contracts\MediaStorageInterface;
use NixPHP\CMS\Models\Media;
use NixPHP\CMS\Storage\EntityStorage;
use function NixPHP\app;

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

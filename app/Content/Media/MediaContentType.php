<?php

declare(strict_types=1);

namespace NixCMS\Content\Media;

use NixCMS\Api\MediaApi;
use NixCMS\Content\ContentTypeInterface;
use NixCMS\Models\Media;
use NixCMS\Repositories\MediaRepository;
use NixCMS\Services\Media\MediaStorageService;

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
            $repository = new MediaRepository();
            $this->api = new MediaApi($repository, new MediaStorageService($repository));
        }

        return $this->api;
    }
}

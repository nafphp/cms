<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Media\DeleteMedia;
use src\Handlers\Api\Media\ListMedia;
use src\Handlers\Api\Media\ServeMedia;
use src\Handlers\Api\Media\UpdateMedia;
use src\Handlers\Api\Media\UploadMedia;

return [
    ApiEndpoint::get('media.serve', '/media/{publicId}/{filename}')
        ->setHandler(ServeMedia::class),

    ApiEndpoint::get('api.admin.media', '/api/admin/media')
        ->requirePermission(Permission::MEDIA_VIEW)
        ->setHandler(ListMedia::class),

    ApiEndpoint::post('api.admin.media.upload', '/api/admin/media/upload')
        ->requirePermission(Permission::MEDIA_UPLOAD)
        ->setHandler(UploadMedia::class),

    ApiEndpoint::post('api.admin.media.update', '/api/admin/media/{id}/update')
        ->requirePermission(Permission::MEDIA_EDIT)
        ->setHandler(UpdateMedia::class),

    ApiEndpoint::post('api.admin.media.delete', '/api/admin/media/{id}/delete')
        ->requirePermission(Permission::MEDIA_DELETE)
        ->setHandler(DeleteMedia::class),
];

<?php

declare(strict_types=1);

use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\Page;

use function Naf\CMS\cms;
use function Naf\I18n\t;
use function Naf\route;
use function Naf\View\render;

return [
    Page::create('admin.media')
        ->setPath('/admin/media')
        ->setTitle('Media')
        ->setLayout('admin')
        ->requirePermission(Permission::MEDIA_VIEW)
        ->setAdminNavigation('Media', icon: 'media', order: 25, key: 'media')
        ->setContent(static fn() => render('admin.media.index', [
            'title'              => t('Media'),
            'pageTitle'          => t('Media'),
            'pageSubtitle'       => t('Upload files once and reuse their public URLs across the CMS.'),
            'media'              => cms()->media()->recent(180),
            'summary'            => cms()->media()->summary(),
            'acceptedExtensions' => cms()->media()->acceptedExtensions(),
            'maxUploadSize'      => cms()->media()->maxUploadSize(),
            'uploadUrl'          => route('api.admin.media.upload'),
            'listUrl'            => route('api.admin.media'),
            'updateUrl'          => '/api/admin/media/{id}/update',
            'deleteUrl'          => '/api/admin/media/{id}/delete',
        ])),
];

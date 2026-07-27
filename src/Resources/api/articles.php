<?php

declare(strict_types=1);

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Api\Admin\ListArticles;
use NixPHP\CMS\Handlers\Api\Article\DeleteArticleLocale;
use NixPHP\CMS\Handlers\Api\Article\DeleteArticleVersion;
use NixPHP\CMS\Handlers\Api\Article\EnqueueArticleTranslation;
use NixPHP\CMS\Handlers\Api\Article\NextArticleVersion;
use NixPHP\CMS\Handlers\Api\Article\PublishArticle;
use NixPHP\CMS\Handlers\Api\Article\ReviewArticle;
use NixPHP\CMS\Handlers\Api\Article\UploadArticleImage;

return [
    ApiEndpoint::get('api.admin.articles', '/api/admin/articles')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setHandler(ListArticles::class),

    ApiEndpoint::get('api.admin.articles.next', '/api/admin/articles/{articleId}/{language}/next')
        ->requirePermission(Permission::ARTICLES_CREATE)
        ->setHandler(NextArticleVersion::class),

    ApiEndpoint::post('admin.articles.image', '/admin/articles/image')
        ->requireAnyPermission(Permission::MEDIA_UPLOAD, Permission::ARTICLES_EDIT)
        ->setHandler(UploadArticleImage::class),

    ApiEndpoint::post('api.admin.articles.publish', '/api/admin/articles/{articleId}/{language}/publish')
        ->requirePermission(Permission::ARTICLES_PUBLISH)
        ->setHandler(PublishArticle::class),

    ApiEndpoint::post('api.admin.articles.review', '/api/admin/articles/{articleId}/{language}/review')
        ->requireAnyPermission(Permission::ARTICLES_REVIEW, Permission::ARTICLES_PUBLISH)
        ->setHandler(ReviewArticle::class),

    ApiEndpoint::post('api.admin.articles.delete', '/api/admin/articles/{articleId}/{language}/delete')
        ->requirePermission(Permission::ARTICLES_DELETE)
        ->setHandler(DeleteArticleLocale::class),

    ApiEndpoint::post('api.admin.articles.version.delete', '/api/admin/articles/{articleId}/{language}/{version}/delete')
        ->requirePermission(Permission::ARTICLES_DELETE)
        ->setHandler(DeleteArticleVersion::class),

    ApiEndpoint::post('api.admin.articles.translate.enqueue', '/api/admin/articles/{articleId}/translate/enqueue')
        ->requirePermission(Permission::ARTICLES_CREATE)
        ->setHandler(EnqueueArticleTranslation::class),
];

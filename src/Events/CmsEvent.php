<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

/** @api */
final class CmsEvent
{
    public const string PAGE_CREATED             = 'cms.page.created';
    public const string PAGE_UPDATED             = 'cms.page.updated';
    public const string PAGE_PUBLISHED           = 'cms.page.published';
    public const string PAGE_DELETED             = 'cms.page.deleted';
    public const string ARTICLE_CREATED          = 'cms.article.created';
    public const string ARTICLE_DRAFT_SAVED      = 'cms.article.draft_saved';
    public const string ARTICLE_PUBLISHED        = 'cms.article.published';
    public const string ARTICLE_VERSION_RESTORED = 'cms.article.version_restored';
    public const string ARTICLE_LOCALE_DELETED   = 'cms.article.locale_deleted';
    public const string ARTICLE_VERSION_DELETED  = 'cms.article.version_deleted';
    public const string FORM_CREATED             = 'cms.form.created';
    public const string FORM_UPDATED             = 'cms.form.updated';
    public const string FORM_DELETED             = 'cms.form.deleted';
    public const string FORM_SUBMITTED           = 'cms.form.submitted';
    public const string MEDIA_UPLOADED           = 'cms.media.uploaded';
    public const string MEDIA_UPDATED            = 'cms.media.updated';
    public const string MEDIA_DELETED            = 'cms.media.deleted';
    public const string NAVIGATION_CREATED       = 'cms.navigation.created';
    public const string NAVIGATION_UPDATED       = 'cms.navigation.updated';
    public const string NAVIGATION_PUBLISHED     = 'cms.navigation.published';
    public const string NAVIGATION_DELETED       = 'cms.navigation.deleted';
}

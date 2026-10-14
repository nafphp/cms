<?php

declare(strict_types=1);

use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Handlers\Page\Article\StoreArticleDraft;
use Naf\CMS\Models\Article;
use Naf\CMS\Page\Page;

use function Naf\CMS\cms;
use function Naf\CMS\user;
use function Naf\I18n\t;
use function Naf\redirect;
use function Naf\request;
use function Naf\route;
use function Naf\View\render;

$articles = static function (): ArticleContentType {
    $type = cms()->contentTypes()->requireByModel(Article::class);
    if (!$type instanceof ArticleContentType) {
        throw new RuntimeException('Registered article content type must be an ArticleContentType.');
    }

    return $type;
};

return [
    Page::create('admin.articles')
        ->setPath('/admin/articles')
        ->setTitle('Article management')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_VIEW)
        ->setAdminNavigation('Articles', parent: 'content', icon: 'article', order: 10, key: 'articles')
        ->setContent(static fn() => render('admin.articles.index', [
            'title'        => t('Article management'),
            'pageSubtitle' => t('Manage article drafts, versions, translations, and publishing state.'),
            'overview'     => $articles()->catalog()->overview(),
        ])),

    Page::create('admin.articles.create')
        ->setPath('/admin/articles/create')
        ->setTitle('Create article')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_CREATE)
        ->setDataHandler(StoreArticleDraft::class, 'POST', Permission::ARTICLES_CREATE)
        ->hideFromAdminNavigation('articles')
        ->setContent(static fn() => render('admin.articles.form', [
            'title'          => t('Create article'),
            'pageSubtitle'   => t('Create and edit article content, metadata, and publishing details.'),
            'mode'           => 'create',
            'categories'     => $articles()->form()->categoryNames(),
            'tagSuggestions' => $articles()->form()->tagNames(),
        ])),

    Page::create('admin.articles.version.create')
        ->setPath('/admin/articles/{articleId}/version/create')
        ->setTitle('Create version')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_CREATE)
        ->setDataHandler(StoreArticleDraft::class, 'POST', Permission::ARTICLES_CREATE)
        ->hideFromAdminNavigation('articles')
        ->setContent(static function (string $articleId) use ($articles) {
            $service                = $articles()->form();
            $queryParams            = request()->getQueryParams();
            $requestedLanguage      = strtolower(trim((string) ($queryParams['language'] ?? '')));
            $requestedSourceVersion = (int) ($queryParams['source_version'] ?? 0);

            $article = $requestedLanguage !== '' && $requestedSourceVersion > 0
                ? $service->findArticleForForm((int) $articleId, $requestedLanguage, $requestedSourceVersion)
                : $service->findArticleForForm((int) $articleId, $requestedLanguage !== '' ? $requestedLanguage : null);

            if ($article === null) {
                return redirect(route('admin.articles'));
            }

            return render('admin.articles.form', [
                'title'          => t('Create version'),
                'pageSubtitle'   => t('Create and edit article content, metadata, and publishing details.'),
                'mode'           => 'version',
                'articleId'      => (int) $articleId,
                'language'       => $requestedLanguage !== '' ? $requestedLanguage : null,
                'article'        => $article,
                'categories'     => $service->categoryNames(),
                'tagSuggestions' => $service->tagNames(),
            ]);
        }),

    Page::create('admin.articles.translation.create')
        ->setPath('/admin/articles/{articleId}/{language}/create')
        ->setTitle('Create translation')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_CREATE)
        ->setDataHandler(StoreArticleDraft::class, 'POST', Permission::ARTICLES_CREATE)
        ->hideFromAdminNavigation('articles')
        ->setContent(static function (string $articleId, string $language) use ($articles) {
            $service = $articles()->form();
            $article = $service->findArticleForForm((int) $articleId, $language);

            if ($article !== null && (int) ($article['article_version_id'] ?? 0) > 0) {
                return redirect(route('admin.articles.edit', [
                    'articleId' => (int) $articleId,
                    'language'  => strtolower(trim($language)),
                ]));
            }

            $sourceArticle = $service->findArticleForForm((int) $articleId);
            if ($sourceArticle === null) {
                return redirect(route('admin.articles'));
            }

            foreach (['article_version_id', 'version', 'status', 'slug', 'title', 'teaser', 'content', 'publishedAt', 'show_teaser'] as $versionField) {
                unset($sourceArticle[$versionField]);
            }

            return render('admin.articles.form', [
                'title'          => t('Create translation'),
                'pageSubtitle'   => t('Create and edit article content, metadata, and publishing details.'),
                'mode'           => 'translation',
                'articleId'      => (int) $articleId,
                'language'       => strtolower(trim($language)),
                'article'        => $sourceArticle,
                'categories'     => $service->categoryNames(),
                'tagSuggestions' => $service->tagNames(),
            ]);
        }),

    Page::create('admin.articles.edit')
        ->setPath('/admin/articles/{articleId}/{language}/edit')
        ->setTitle('Edit draft')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_EDIT)
        ->setDataHandler(StoreArticleDraft::class, 'POST', Permission::ARTICLES_EDIT)
        ->hideFromAdminNavigation('articles')
        ->setContent(static function (string $articleId, string $language) use ($articles) {
            $service = $articles()->form();
            $article = $service->findArticleForForm((int) $articleId, $language);

            if ($article === null) {
                return redirect(route('admin.articles.translation.create', [
                    'articleId' => (int) $articleId,
                    'language'  => strtolower(trim($language)),
                ]));
            }

            if (($article['status'] ?? '') === ArticleStatus::PUBLISHED->value) {
                return redirect(route('admin.articles.version.create', ['articleId' => (int) $articleId])
                    . '?language=' . rawurlencode(strtolower(trim($language))));
            }

            return render('admin.articles.form', [
                'title'          => t('Edit draft'),
                'pageSubtitle'   => t('Create and edit article content, metadata, and publishing details.'),
                'mode'           => 'edit',
                'articleId'      => (int) $articleId,
                'language'       => strtolower(trim($language)),
                'article'        => $article,
                'categories'     => $service->categoryNames(),
                'tagSuggestions' => $service->tagNames(),
            ]);
        }),

    Page::create('admin.articles.version.edit')
        ->setPath('/admin/articles/{articleId}/{language}/{version}/edit')
        ->setTitle('Edit version')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_EDIT)
        ->setDataHandler(StoreArticleDraft::class, 'POST', Permission::ARTICLES_EDIT)
        ->hideFromAdminNavigation('articles')
        ->setContent(static function (string $articleId, string $language, string $version) use ($articles) {
            $service = $articles()->form();
            $article = $service->findArticleForForm((int) $articleId, $language, (int) $version);

            if ($article === null) {
                return redirect(route('admin.articles'));
            }

            if (($article['status'] ?? '') === ArticleStatus::PUBLISHED->value) {
                return redirect(route('admin.articles.version.create', ['articleId' => (int) $articleId])
                    . '?language=' . rawurlencode(strtolower(trim($language)))
                    . '&source_version=' . rawurlencode((string) (int) $version));
            }

            return render('admin.articles.form', [
                'title'          => t('Edit version'),
                'pageSubtitle'   => t('Create and edit article content, metadata, and publishing details.'),
                'mode'           => 'edit',
                'articleId'      => (int) $articleId,
                'language'       => strtolower(trim($language)),
                'version'        => (int) $version,
                'article'        => $article,
                'categories'     => $service->categoryNames(),
                'tagSuggestions' => $service->tagNames(),
            ]);
        }),

    Page::create('admin.articles.preview')
        ->setPath('/admin/articles/{articleId}/{language}/preview')
        ->setTitle('Preview article')
        ->setLayout('admin')
        ->requirePermission(Permission::ARTICLES_EDIT)
        ->hideFromAdminNavigation('articles')
        ->setContent(static function (string $articleId, string $language) use ($articles) {
            $type    = $articles();
            $service = $type->form();
            $article = $service->findArticleForForm((int) $articleId, $language);
            if ($article === null) {
                return redirect(route('admin.articles'));
            }

            $renderer = $type->renderer($article);

            return render('admin.articles.preview', [
                'title'         => t('Preview article'),
                'pageSubtitle'  => t('Review the article exactly before publishing decisions.'),
                'article'       => $renderer,
                'reviewActions' => $service->reviewActions($renderer->versionId()),
                'canReview'     => user()?->can(Permission::ARTICLES_REVIEW) === true
                    || user()?->can(Permission::ARTICLES_PUBLISH) === true,
                'canDirectPublish' => user()?->can(Permission::ARTICLES_PUBLISH) === true,
                'isReviewable'     => in_array($renderer->status(), [
                    ArticleStatus::DRAFT->value,
                    ArticleStatus::DECLINED->value,
                ], true),
            ]);
        }),
];

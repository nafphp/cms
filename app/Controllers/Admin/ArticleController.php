<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\AbstractController;
use Cms\Enums\ArticleStatus;
use Cms\Enums\Permission;
use Cms\Services\Content\ArticleCatalogService;
use Cms\Services\Content\ArticleFormService;
use Cms\Services\Content\ArticleRenderer;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\redirect;
use function NixPHP\request;
use function NixPHP\route;
use function NixPHP\View\render;

final class ArticleController extends AbstractController
{
    public function index(): ResponseInterface
    {
        $this->requirePermission(Permission::ARTICLES_VIEW);

        return render('admin.articles.index', [
            'title' => 'Article management',
            'pageSubtitle' => 'Manage article drafts, versions, translations, and publishing state.',
            'overview' => (new ArticleCatalogService())->overview(),
        ]);
    }

    public function create(): ResponseInterface
    {
        $this->requirePermission(Permission::ARTICLES_CREATE);

        return render('admin.articles.form', [
            'title' => 'Artikel erstellen',
            'pageSubtitle' => 'Create and edit article content, metadata, and publishing details.',
            'mode' => 'create',
            'categories' => (new ArticleFormService())->categoryNames(),
            'tagSuggestions' => (new ArticleFormService())->tagNames(),
        ]);
    }

    public function edit(string $articleId, string $language): ResponseInterface
    {
        $service = new ArticleFormService();
        $this->requirePermission(Permission::ARTICLES_EDIT);
        $article = $service->findArticleForForm((int) $articleId, $language);

        if ($article === null) {
            return redirect(route('admin.articles.translation.create', [
                'articleId' => (int) $articleId,
                'language' => strtolower(trim($language)),
            ]));
        }

        if (($article['status'] ?? '') === ArticleStatus::PUBLISHED->value) {
            return redirect(route('admin.articles.version.create', ['articleId' => (int) $articleId])
                . '?language=' . rawurlencode(strtolower(trim($language))));
        }

        return render('admin.articles.form', [
            'title' => 'Edit draft',
            'pageSubtitle' => 'Create and edit article content, metadata, and publishing details.',
            'mode' => 'edit',
            'articleId' => (int) $articleId,
            'language' => strtolower(trim($language)),
            'article' => $article,
            'categories' => $service->categoryNames(),
            'tagSuggestions' => $service->tagNames(),
        ]);
    }

    public function editVersion(string $articleId, string $language, string $version): ResponseInterface
    {
        $service = new ArticleFormService();
        $this->requirePermission(Permission::ARTICLES_EDIT);
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
            'title' => 'Edit version',
            'pageSubtitle' => 'Create and edit article content, metadata, and publishing details.',
            'mode' => 'edit',
            'articleId' => (int) $articleId,
            'language' => strtolower(trim($language)),
            'version' => (int) $version,
            'article' => $article,
            'categories' => $service->categoryNames(),
            'tagSuggestions' => $service->tagNames(),
        ]);
    }

    public function createTranslation(string $articleId, string $language): ResponseInterface
    {
        $service = new ArticleFormService();
        $this->requirePermission(Permission::ARTICLES_CREATE);
        $article = $service->findArticleForForm((int) $articleId, $language);

        if ($article !== null && (int) ($article['article_version_id'] ?? 0) > 0) {
            return redirect(route('admin.articles.edit', [
                'articleId' => (int) $articleId,
                'language' => strtolower(trim($language)),
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
            'title' => 'Create translation',
            'pageSubtitle' => 'Create and edit article content, metadata, and publishing details.',
            'mode' => 'translation',
            'articleId' => (int) $articleId,
            'language' => strtolower(trim($language)),
            'article' => $sourceArticle,
            'categories' => $service->categoryNames(),
            'tagSuggestions' => $service->tagNames(),
        ]);
    }

    public function createVersion(string $articleId): ResponseInterface
    {
        $service = new ArticleFormService();
        $this->requirePermission(Permission::ARTICLES_CREATE);
        $queryParams = request()->getQueryParams();
        $requestedLanguage = strtolower(trim((string) ($queryParams['language'] ?? '')));
        $requestedSourceVersion = (int) ($queryParams['source_version'] ?? 0);

        $article = $requestedLanguage !== '' && $requestedSourceVersion > 0
            ? $service->findArticleForForm((int) $articleId, $requestedLanguage, $requestedSourceVersion)
            : $service->findArticleForForm((int) $articleId, $requestedLanguage !== '' ? $requestedLanguage : null);

        if ($article === null) {
            return redirect(route('admin.articles'));
        }

        return render('admin.articles.form', [
            'title' => 'Create version',
            'pageSubtitle' => 'Create and edit article content, metadata, and publishing details.',
            'mode' => 'version',
            'articleId' => (int) $articleId,
            'language' => $requestedLanguage !== '' ? $requestedLanguage : null,
            'article' => $article,
            'categories' => $service->categoryNames(),
            'tagSuggestions' => $service->tagNames(),
        ]);
    }

    public function preview(string $articleId, string $language): ResponseInterface
    {
        $service = new ArticleFormService();
        $this->requirePermission(Permission::ARTICLES_EDIT);
        $article = $service->findArticleForForm((int) $articleId, $language);
        if ($article === null) {
            return redirect(route('admin.articles'));
        }

        $renderer = new ArticleRenderer($article);

        return render('admin.articles.preview', [
            'title' => 'Preview article',
            'pageSubtitle' => 'Review the article exactly before publishing decisions.',
            'article' => $renderer,
            'reviewActions' => $service->reviewActions($renderer->versionId()),
            'canReview' => \user()?->can(\Cms\Enums\Permission::ARTICLES_REVIEW) === true
                || \user()?->can(\Cms\Enums\Permission::ARTICLES_PUBLISH) === true,
            'canDirectPublish' => \user()?->can(\Cms\Enums\Permission::ARTICLES_PUBLISH) === true,
            'isReviewable' => in_array($renderer->status(), [
                ArticleStatus::DRAFT->value,
                ArticleStatus::DECLINED->value,
            ], true),
        ]);
    }
}

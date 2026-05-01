<?php

declare(strict_types=1);

namespace NixCMS\Content\Article;

use NixCMS\Api\ArticleApi;
use NixCMS\Content\ContentTypeInterface;
use NixCMS\Models\Article;
use NixCMS\Repositories\ArticleRepository;
use NixCMS\Services\Content\ArticleCatalogService;
use NixCMS\Services\Content\ArticleFormService;
use NixCMS\Services\Content\ArticleRenderer;
use function NixPHP\ORM\repo;

final class ArticleContentType implements ContentTypeInterface
{
    private ?ArticleApi $api = null;
    private ?BlockRegistry $blockRegistry = null;
    private ?ArticleFormService $formService = null;
    private ?ArticleCatalogService $catalogService = null;

    public function id(): string
    {
        return 'article';
    }

    public function label(): string
    {
        return 'Article';
    }

    public function modelClass(): string
    {
        return Article::class;
    }

    public function api(): ArticleApi
    {
        if ($this->api === null) {
            $this->api = new ArticleApi(repo(ArticleRepository::class));
        }

        return $this->api;
    }

    public function form(): ArticleFormService
    {
        return $this->formService ??= new ArticleFormService();
    }

    public function catalog(): ArticleCatalogService
    {
        return $this->catalogService ??= new ArticleCatalogService();
    }

    public function blocks(): BlockRegistry
    {
        return $this->blockRegistry ??= new BlockRegistry();
    }

    /**
     * @param array<string, mixed> $article
     */
    public function renderer(array $article): ArticleRenderer
    {
        return new ArticleRenderer($article, $this->blocks());
    }
}

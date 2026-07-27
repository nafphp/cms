<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article;

use NixPHP\CMS\Api\ArticleApi;
use NixPHP\CMS\Content\Article\BlockRegistry;
use NixPHP\CMS\Content\ContentTypeInterface;
use NixPHP\CMS\Models\Article;
use NixPHP\CMS\Repositories\ArticleRepository;
use NixPHP\CMS\Services\Content\ArticleCatalogService;
use NixPHP\CMS\Services\Content\ArticleFormService;
use NixPHP\CMS\Services\Content\ArticleRenderer;
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

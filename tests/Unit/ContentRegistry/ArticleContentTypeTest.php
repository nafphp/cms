<?php

declare(strict_types=1);

namespace Tests\Unit\ContentRegistry;

use NixCMS\Content\Article\ArticleContentType;
use NixCMS\Content\Article\BlockRegistry;
use NixCMS\Models\Article;
use NixCMS\Services\Content\ArticleCatalogService;
use NixCMS\Services\Content\ArticleFormService;
use NixCMS\Services\Content\ArticleRenderer;
use PHPUnit\Framework\TestCase;

final class ArticleContentTypeTest extends TestCase
{
    public function testArticleContentTypeDescribesArticleModel(): void
    {
        $type = new ArticleContentType();

        self::assertSame('article', $type->id());
        self::assertSame('Article', $type->label());
        self::assertSame(Article::class, $type->modelClass());
        self::assertInstanceOf(ArticleFormService::class, $type->form());
        self::assertInstanceOf(ArticleCatalogService::class, $type->catalog());
    }

    public function testArticleContentTypeOwnsArticleBlockRegistryAndRenderer(): void
    {
        $type = new ArticleContentType();

        self::assertInstanceOf(BlockRegistry::class, $type->blocks());
        self::assertSame($type->blocks(), $type->blocks());
        self::assertInstanceOf(ArticleRenderer::class, $type->renderer(['content' => '{"blocks":[]}']));
    }
}

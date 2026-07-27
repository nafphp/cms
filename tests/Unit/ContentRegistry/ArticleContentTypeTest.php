<?php

declare(strict_types=1);

namespace Tests\Unit\ContentRegistry;

use src\Content\Article\ArticleContentType;
use src\Content\Article\BlockRegistry;
use src\Models\Article;
use src\Services\Content\ArticleCatalogService;
use src\Services\Content\ArticleFormService;
use src\Services\Content\ArticleRenderer;
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

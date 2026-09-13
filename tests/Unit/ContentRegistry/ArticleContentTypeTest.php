<?php

declare(strict_types=1);

namespace Tests\Unit\ContentRegistry;

use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Content\Article\BlockRegistry;
use Naf\CMS\Models\Article;
use Naf\CMS\Services\Content\ArticleCatalogService;
use Naf\CMS\Services\Content\ArticleFormService;
use Naf\CMS\Services\Content\ArticleRenderer;
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

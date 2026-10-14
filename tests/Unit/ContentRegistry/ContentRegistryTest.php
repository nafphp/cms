<?php

declare(strict_types=1);

namespace Tests\Unit\ContentRegistry;

use LogicException;
use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Content\ContentRegistry;
use Naf\CMS\Models\Article;
use PHPUnit\Framework\TestCase;

final class ContentRegistryTest extends TestCase
{
    public function testRegistryFindsContentTypeByIdAndModel(): void
    {
        $type     = new ArticleContentType();
        $registry = new ContentRegistry();
        $registry->add($type);

        self::assertSame($type, $registry->get('article'));
        self::assertSame($type, $registry->findByModel(Article::class));
        self::assertSame($type, $registry->requireByModel(Article::class));
    }

    public function testRegistryRequiresExplicitReplacement(): void
    {
        $registry = new ContentRegistry();
        $registry->add(new ArticleContentType());

        self::expectException(LogicException::class);
        self::expectExceptionMessage('Content type "article" is already registered.');

        $registry->add(new ArticleContentType());
    }

    public function testRegistryCanReplaceContentType(): void
    {
        $registry = new ContentRegistry();
        $registry->add(new ArticleContentType());

        $replacement = new ArticleContentType();
        $registry->add($replacement, replace: true);

        self::assertSame($replacement, $registry->get('article'));
    }
}

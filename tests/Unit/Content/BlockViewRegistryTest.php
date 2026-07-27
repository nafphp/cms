<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use src\Content\BlockViewRegistry;
use PHPUnit\Framework\TestCase;

final class BlockViewRegistryTest extends TestCase
{
    public function testDefaultsExposeProductionBlockViews(): void
    {
        $registry = new BlockViewRegistry();

        self::assertSame('page.blocks.header', $registry->viewFor('header'));
        self::assertSame('page.blocks.chart', $registry->viewFor('chart'));
        self::assertSame('page.blocks.article_title', $registry->viewFor('article-title'));
        self::assertSame('page.blocks.article_related', $registry->viewFor('article-related'));
        self::assertSame('page.blocks.section', $registry->viewFor('section'));
        self::assertSame('page.blocks.columns', $registry->viewFor('columns'));
        self::assertSame('page.blocks.text', $registry->viewFor(' text '));
        self::assertSame('page.blocks.chart', $registry->all()['chart']);
    }

    public function testAddRegistersPluginBlockView(): void
    {
        $registry = new BlockViewRegistry();

        $registry->add('MyCustom', 'page.blocks.mycustom');

        self::assertSame('page.blocks.mycustom', $registry->viewFor('mycustom'));
    }

    public function testAliasesUseExplicitDefaultViews(): void
    {
        $registry = new BlockViewRegistry();

        self::assertSame('page.blocks.form', $registry->viewFor('contact-form'));
        self::assertSame('page.blocks.text', $registry->viewFor('aside'));
    }

    public function testUnknownBlockTypeFallsBackToPlaceholder(): void
    {
        self::assertSame('page.blocks.placeholder', (new BlockViewRegistry())->viewFor('unknown'));
    }
}

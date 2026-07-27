<?php

declare(strict_types=1);

namespace Tests\Support;

use NixPHP\CMS\Content\Page\Block\BlockTypeInterface;
use NixPHP\CMS\Content\Page\Block\Types\ArticleContentBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticleMetaBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticlesBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ArticleTitleBlockType;
use NixPHP\CMS\Content\Page\Block\Types\AsideBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ChartBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ColumnsBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ContactFormBlockType;
use NixPHP\CMS\Content\Page\Block\Types\CtaBlockType;
use NixPHP\CMS\Content\Page\Block\Types\EmbedBlockType;
use NixPHP\CMS\Content\Page\Block\Types\FeatureBlockType;
use NixPHP\CMS\Content\Page\Block\Types\FormBlockType;
use NixPHP\CMS\Content\Page\Block\Types\FooterBlockType;
use NixPHP\CMS\Content\Page\Block\Types\GridBlockType;
use NixPHP\CMS\Content\Page\Block\Types\HeaderBlockType;
use NixPHP\CMS\Content\Page\Block\Types\HeadingBlockType;
use NixPHP\CMS\Content\Page\Block\Types\HeroBlockType;
use NixPHP\CMS\Content\Page\Block\Types\ImageBlockType;
use NixPHP\CMS\Content\Page\Block\Types\MapBlockType;
use NixPHP\CMS\Content\Page\Block\Types\MediaBlockType;
use NixPHP\CMS\Content\Page\Block\Types\NavigationBlockType;
use NixPHP\CMS\Content\Page\Block\Types\QuoteBlockType;
use NixPHP\CMS\Content\Page\Block\Types\SectionBlockType;
use NixPHP\CMS\Content\Page\Block\Types\StackBlockType;
use NixPHP\CMS\Content\Page\Block\Types\StatsBlockType;
use NixPHP\CMS\Content\Page\Block\Types\TextBlockType;

final class CoreBlockTypes
{
    /**
     * @return array<int, BlockTypeInterface>
     */
    public static function all(): array
    {
        return [
            new ArticleContentBlockType(),
            new ArticleMetaBlockType(),
            new ArticleRelatedBlockType(),
            new ArticlesBlockType(),
            new ArticleTitleBlockType(),
            new AsideBlockType(),
            new ChartBlockType(),
            new ColumnsBlockType(),
            new ContactFormBlockType(),
            new CtaBlockType(),
            new EmbedBlockType(),
            new FeatureBlockType(),
            new FormBlockType(),
            new FooterBlockType(),
            new GridBlockType(),
            new HeaderBlockType(),
            new HeadingBlockType(),
            new HeroBlockType(),
            new ImageBlockType(),
            new MapBlockType(),
            new MediaBlockType(),
            new NavigationBlockType(),
            new QuoteBlockType(),
            new SectionBlockType(),
            new StackBlockType(),
            new StatsBlockType(),
            new TextBlockType(),
        ];
    }
}

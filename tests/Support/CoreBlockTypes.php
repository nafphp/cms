<?php

declare(strict_types=1);

namespace Tests\Support;

use NixCMS\Content\Page\Block\BlockTypeInterface;
use NixCMS\Content\Page\Block\Types\ArticleContentBlockType;
use NixCMS\Content\Page\Block\Types\ArticleMetaBlockType;
use NixCMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use NixCMS\Content\Page\Block\Types\ArticlesBlockType;
use NixCMS\Content\Page\Block\Types\ArticleTitleBlockType;
use NixCMS\Content\Page\Block\Types\AsideBlockType;
use NixCMS\Content\Page\Block\Types\ChartBlockType;
use NixCMS\Content\Page\Block\Types\ColumnsBlockType;
use NixCMS\Content\Page\Block\Types\ContactFormBlockType;
use NixCMS\Content\Page\Block\Types\CtaBlockType;
use NixCMS\Content\Page\Block\Types\EmbedBlockType;
use NixCMS\Content\Page\Block\Types\FeatureBlockType;
use NixCMS\Content\Page\Block\Types\FormBlockType;
use NixCMS\Content\Page\Block\Types\FooterBlockType;
use NixCMS\Content\Page\Block\Types\GridBlockType;
use NixCMS\Content\Page\Block\Types\HeaderBlockType;
use NixCMS\Content\Page\Block\Types\HeadingBlockType;
use NixCMS\Content\Page\Block\Types\HeroBlockType;
use NixCMS\Content\Page\Block\Types\ImageBlockType;
use NixCMS\Content\Page\Block\Types\MapBlockType;
use NixCMS\Content\Page\Block\Types\MediaBlockType;
use NixCMS\Content\Page\Block\Types\NavigationBlockType;
use NixCMS\Content\Page\Block\Types\QuoteBlockType;
use NixCMS\Content\Page\Block\Types\SectionBlockType;
use NixCMS\Content\Page\Block\Types\StackBlockType;
use NixCMS\Content\Page\Block\Types\StatsBlockType;
use NixCMS\Content\Page\Block\Types\TextBlockType;

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

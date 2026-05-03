<?php

declare(strict_types=1);

use NixCMS\Content\Page\Block\Types\ArticleContentBlockType;
use NixCMS\Content\Page\Block\Types\ArticleMetaBlockType;
use NixCMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use NixCMS\Content\Page\Block\Types\ArticleTitleBlockType;
use NixCMS\Content\Page\Block\Types\ArticlesBlockType;
use NixCMS\Content\Page\Block\Types\AsideBlockType;
use NixCMS\Content\Page\Block\Types\ChartBlockType;
use NixCMS\Content\Page\Block\Types\ColumnsBlockType;
use NixCMS\Content\Page\Block\Types\ContactFormBlockType;
use NixCMS\Content\Page\Block\Types\CtaBlockType;
use NixCMS\Content\Page\Block\Types\EmbedBlockType;
use NixCMS\Content\Page\Block\Types\FeatureBlockType;
use NixCMS\Content\Page\Block\Types\FooterBlockType;
use NixCMS\Content\Page\Block\Types\FormBlockType;
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

return [
    new SectionBlockType(),
    new StackBlockType(),
    new ColumnsBlockType(),
    new GridBlockType(),
    new HeroBlockType(),
    new CtaBlockType(),
    new FeatureBlockType(),
    new MediaBlockType(),
    new ImageBlockType(),
    new EmbedBlockType(),
    new MapBlockType(),
    new QuoteBlockType(),
    new StatsBlockType(),
    new ChartBlockType(),
    new FormBlockType(),
    new ContactFormBlockType(),
    new HeaderBlockType(),
    new FooterBlockType(),
    new AsideBlockType(),
    new HeadingBlockType(),
    new TextBlockType(),
    new ArticlesBlockType(),
    new ArticleTitleBlockType(),
    new ArticleContentBlockType(),
    new ArticleMetaBlockType(),
    new ArticleRelatedBlockType(),
    new NavigationBlockType(),
];

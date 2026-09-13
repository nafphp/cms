<?php

declare(strict_types=1);

namespace Tests\Support;

use Naf\CMS\Content\Page\Block\BlockTypeInterface;
use Naf\CMS\Content\Page\Block\Types\ArticleContentBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticleMetaBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticleRelatedBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticlesBlockType;
use Naf\CMS\Content\Page\Block\Types\ArticleTitleBlockType;
use Naf\CMS\Content\Page\Block\Types\AsideBlockType;
use Naf\CMS\Content\Page\Block\Types\ChartBlockType;
use Naf\CMS\Content\Page\Block\Types\ColumnsBlockType;
use Naf\CMS\Content\Page\Block\Types\ContactFormBlockType;
use Naf\CMS\Content\Page\Block\Types\CtaBlockType;
use Naf\CMS\Content\Page\Block\Types\EmbedBlockType;
use Naf\CMS\Content\Page\Block\Types\FeatureBlockType;
use Naf\CMS\Content\Page\Block\Types\FormBlockType;
use Naf\CMS\Content\Page\Block\Types\FooterBlockType;
use Naf\CMS\Content\Page\Block\Types\GridBlockType;
use Naf\CMS\Content\Page\Block\Types\HeaderBlockType;
use Naf\CMS\Content\Page\Block\Types\HeadingBlockType;
use Naf\CMS\Content\Page\Block\Types\HeroBlockType;
use Naf\CMS\Content\Page\Block\Types\ImageBlockType;
use Naf\CMS\Content\Page\Block\Types\MapBlockType;
use Naf\CMS\Content\Page\Block\Types\MediaBlockType;
use Naf\CMS\Content\Page\Block\Types\NavigationBlockType;
use Naf\CMS\Content\Page\Block\Types\QuoteBlockType;
use Naf\CMS\Content\Page\Block\Types\SectionBlockType;
use Naf\CMS\Content\Page\Block\Types\StackBlockType;
use Naf\CMS\Content\Page\Block\Types\StatsBlockType;
use Naf\CMS\Content\Page\Block\Types\TextBlockType;

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

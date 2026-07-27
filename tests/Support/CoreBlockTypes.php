<?php

declare(strict_types=1);

namespace Tests\Support;

use src\Content\Page\Block\BlockTypeInterface;
use src\Content\Page\Block\Types\ArticleContentBlockType;
use src\Content\Page\Block\Types\ArticleMetaBlockType;
use src\Content\Page\Block\Types\ArticleRelatedBlockType;
use src\Content\Page\Block\Types\ArticlesBlockType;
use src\Content\Page\Block\Types\ArticleTitleBlockType;
use src\Content\Page\Block\Types\AsideBlockType;
use src\Content\Page\Block\Types\ChartBlockType;
use src\Content\Page\Block\Types\ColumnsBlockType;
use src\Content\Page\Block\Types\ContactFormBlockType;
use src\Content\Page\Block\Types\CtaBlockType;
use src\Content\Page\Block\Types\EmbedBlockType;
use src\Content\Page\Block\Types\FeatureBlockType;
use src\Content\Page\Block\Types\FormBlockType;
use src\Content\Page\Block\Types\FooterBlockType;
use src\Content\Page\Block\Types\GridBlockType;
use src\Content\Page\Block\Types\HeaderBlockType;
use src\Content\Page\Block\Types\HeadingBlockType;
use src\Content\Page\Block\Types\HeroBlockType;
use src\Content\Page\Block\Types\ImageBlockType;
use src\Content\Page\Block\Types\MapBlockType;
use src\Content\Page\Block\Types\MediaBlockType;
use src\Content\Page\Block\Types\NavigationBlockType;
use src\Content\Page\Block\Types\QuoteBlockType;
use src\Content\Page\Block\Types\SectionBlockType;
use src\Content\Page\Block\Types\StackBlockType;
use src\Content\Page\Block\Types\StatsBlockType;
use src\Content\Page\Block\Types\TextBlockType;

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

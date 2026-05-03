<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use NixCMS\Support\Cms;
use NixCMS\Visualization\VisualizationRegistry;
use PHPUnit\Framework\TestCase;

final class CmsTest extends TestCase
{
    public function testCmsThrowsForUnsupportedContentType(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('No content type registered for "stdClass". Register one through cms()->contentTypes()->add(...).');

        $cms = new Cms();
        $cms->content(\stdClass::class);
    }

    public function testCmsExposesVisualizationRegistry(): void
    {
        $cms = new Cms();

        self::assertInstanceOf(VisualizationRegistry::class, $cms->visualizations());
        self::assertSame($cms->visualizations(), $cms->visualizations());
    }

    public function testCmsLoadsDefaultFrontendNavigation(): void
    {
        $cms = new Cms();
        $navigation = $cms->navigationRegistry()->getNavigation('main');

        self::assertNotNull($navigation);
        self::assertSame('Main navigation', $navigation->title());
        self::assertSame(['Home', 'Articles'], array_column($navigation->toArray()['items'], 'label'));
    }
}

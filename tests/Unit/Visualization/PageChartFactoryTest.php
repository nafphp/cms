<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use NixCMS\Visualization\PageChartFactory;
use PHPUnit\Framework\TestCase;

final class PageChartFactoryTest extends TestCase
{
    public function testFactoryBuildsChartFromPageBlockProps(): void
    {
        $chart = (new PageChartFactory())->fromBlockProps([
            'chartType' => 'line',
            'title' => 'Publishing',
            'metricCount' => 2,
            'label1' => 'Draft',
            'value1' => 3,
            'color1' => '#3b82f6',
            'label2' => 'Published',
            'value2' => 9,
            'color2' => '#22c55e',
        ], 'main');

        self::assertSame('line', $chart->type);
        self::assertSame('Publishing', $chart->title);
        self::assertSame(['Draft', 'Published'], $chart->labels);
        self::assertSame([3.0, 9.0], $chart->datasets[0]->values);
        self::assertSame(['#3b82f6', '#22c55e'], $chart->options['colors']);
    }

    public function testFactoryClampsMetricCountAndHeight(): void
    {
        $chart = (new PageChartFactory())->fromBlockProps([
            'chartType' => 'pie',
            'metricCount' => 99,
            'height' => 900,
        ]);

        self::assertSame('pie', $chart->type);
        self::assertCount(6, $chart->labels);
        self::assertSame(720, $chart->height);
    }

    public function testFactoryBuildsMetricBarsChart(): void
    {
        $chart = (new PageChartFactory())->fromBlockProps([
            'chartType' => 'metric-bars',
            'metricCount' => 1,
            'label1' => 'Review',
            'value1' => 7,
        ]);

        self::assertSame('metric-bars', $chart->type);
        self::assertSame(['Review'], $chart->labels);
        self::assertSame(180, $chart->height);
    }

    public function testFactoryFallsBackToBarForUnknownChartType(): void
    {
        $chart = (new PageChartFactory())->fromBlockProps([
            'chartType' => 'radar',
            'metricCount' => 1,
            'label1' => 'Draft',
            'value1' => 2,
        ]);

        self::assertSame('bar', $chart->type);
        self::assertSame(['Draft'], $chart->labels);
    }

    public function testFactoryBuildsStableIdsFromPrefixAndProps(): void
    {
        $props = [
            'chartType' => 'bar',
            'metricCount' => 1,
            'label1' => 'Draft',
            'value1' => 2,
        ];

        $left = (new PageChartFactory())->fromBlockProps($props, 'hero');
        $right = (new PageChartFactory())->fromBlockProps($props, 'hero');

        self::assertSame($left->id, $right->id);
        self::assertStringStartsWith('hero-', $left->id);
    }
}

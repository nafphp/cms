<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use Naf\CMS\Visualization\PageChartFactory;
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
        self::assertCount(24, $chart->labels);
        self::assertSame(720, $chart->height);
    }

    public function testFactoryPrefersDynamicMetrics(): void
    {
        $chart = (new PageChartFactory())->fromBlockProps([
            'chartType' => 'bar',
            'metricCount' => 1,
            'label1' => 'Legacy',
            'value1' => 1,
            'metrics' => [
                ['label' => 'Draft', 'value' => 3, 'color' => '#3b82f6'],
                ['label' => 'Review', 'value' => 7, 'color' => '#f59e0b'],
                ['label' => 'Published', 'value' => 11, 'color' => '#22c55e'],
                ['label' => 'Scheduled', 'value' => 5, 'color' => '#8b5cf6'],
            ],
        ]);

        self::assertSame(['Draft', 'Review', 'Published', 'Scheduled'], $chart->labels);
        self::assertSame([3.0, 7.0, 11.0, 5.0], $chart->datasets[0]->values);
        self::assertSame(['#3b82f6', '#f59e0b', '#22c55e', '#8b5cf6'], $chart->options['colors']);
    }

    public function testFactoryKeepsPopulatedLegacyMetricsVisible(): void
    {
        $chart = (new PageChartFactory())->fromBlockProps([
            'chartType' => 'bar',
            'metricCount' => 3,
            'label1' => 'Draft',
            'value1' => 3,
            'label4' => 'Scheduled',
            'value4' => 5,
        ]);

        self::assertCount(4, $chart->labels);
        self::assertSame('Scheduled', $chart->labels[3]);
        self::assertSame(5.0, $chart->datasets[0]->values[3]);
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

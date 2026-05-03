<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use NixCMS\Visualization\ChartDefinition;
use PHPUnit\Framework\TestCase;

final class ChartDefinitionTest extends TestCase
{
    public function testBarChartNormalizesIdAndKeepsValues(): void
    {
        $chart = ChartDefinition::bar(
            'Article Status!',
            ['Draft' => 2, 'Published' => 4],
            'Workflow',
        );

        self::assertSame('article-status', $chart->id);
        self::assertSame('bar', $chart->type);
        self::assertSame(['Draft', 'Published'], $chart->labels);
        self::assertSame([2, 4], $chart->datasets[0]->values);
        self::assertFalse($chart->isEmpty());
    }

    public function testChartIsEmptyWhenAllValuesAreZero(): void
    {
        $chart = ChartDefinition::doughnut('empty', ['Draft' => 0, 'Published' => 0]);

        self::assertTrue($chart->isEmpty());
    }

    public function testMetricBarsTypeCanBeCreatedDirectly(): void
    {
        $chart = ChartDefinition::make(
            'metric-bars',
            'metric-bars',
            ['Draft'],
            [\NixCMS\Visualization\ChartDataset::make('Value', [4])],
        );

        self::assertSame('metric-bars', $chart->type);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use NixPHP\CMS\Visualization\ChartDataset;
use NixPHP\CMS\Visualization\ChartDefinition;
use NixPHP\CMS\Visualization\EChartsOptionsFactory;
use PHPUnit\Framework\TestCase;

final class EChartsOptionsFactoryTest extends TestCase
{
    public function testMetricBarsHideLegendAndUseCompactGrid(): void
    {
        $options = (new EChartsOptionsFactory())->make(ChartDefinition::make(
            'metric-bars',
            'metric-bars',
            ['Draft', 'Review', 'Published'],
            [ChartDataset::make('Value', [12, 7, 24])],
        ));

        self::assertSame(false, $options['legend']['show']);
        self::assertSame(false, $options['legend']['selectedMode']);
        self::assertSame(8, $options['grid']['top']);
        self::assertSame(12, $options['series'][0]['barWidth']);
    }
}

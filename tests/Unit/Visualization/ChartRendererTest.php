<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use Naf\CMS\Visualization\ChartDefinition;
use Naf\CMS\Visualization\ChartRenderer;
use PHPUnit\Framework\TestCase;

final class ChartRendererTest extends TestCase
{
    public function testRenderOutputsChartPayloadForPhpRendering(): void
    {
        $html = (new ChartRenderer())->render(ChartDefinition::bar(
            'dashboard chart',
            ['Draft' => 3],
            'Workflow',
            'Latest versions',
        ));

        self::assertStringContainsString('data-nafcms-chart="dashboard-chart"', $html);
        self::assertStringContainsString('data-nafcms-chart-options', $html);
        self::assertStringContainsString('"type":"bar"', $html);
        self::assertStringContainsString('Workflow', $html);
        self::assertStringContainsString('Latest versions', $html);
    }
}

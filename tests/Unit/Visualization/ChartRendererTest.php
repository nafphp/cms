<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use NixPHP\CMS\Visualization\ChartDefinition;
use NixPHP\CMS\Visualization\ChartRenderer;
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

        self::assertStringContainsString('data-nixcms-chart="dashboard-chart"', $html);
        self::assertStringContainsString('data-nixcms-chart-options', $html);
        self::assertStringContainsString('"type":"bar"', $html);
        self::assertStringContainsString('Workflow', $html);
        self::assertStringContainsString('Latest versions', $html);
    }
}

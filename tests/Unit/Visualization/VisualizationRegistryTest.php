<?php

declare(strict_types=1);

namespace Tests\Unit\Visualization;

use NixCMS\Visualization\ChartDefinition;
use NixCMS\Visualization\VisualizationRegistry;
use PHPUnit\Framework\TestCase;

final class VisualizationRegistryTest extends TestCase
{
    public function testRegistryRendersRegisteredChartById(): void
    {
        $registry = new VisualizationRegistry();
        $registry->add(ChartDefinition::bar('status', ['Draft' => 1]));

        self::assertStringContainsString('data-nixcms-chart="status"', $registry->render('status'));
    }

    public function testRegistryRendersChartDefinitionDirectly(): void
    {
        $registry = new VisualizationRegistry();

        self::assertStringContainsString(
            'data-nixcms-chart="inline"',
            $registry->render(ChartDefinition::bar('inline', ['Draft' => 1])),
        );
    }

    public function testRegistryProtectsChartIdsByDefault(): void
    {
        $registry = new VisualizationRegistry();
        $registry->add(ChartDefinition::bar('status', ['Draft' => 1]));

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Chart "status" is already registered.');

        $registry->add(ChartDefinition::bar('status', ['Draft' => 2]));
    }

    public function testRegistryThrowsForUnknownChartId(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Chart "missing" is not registered.');

        (new VisualizationRegistry())->render('missing');
    }
}

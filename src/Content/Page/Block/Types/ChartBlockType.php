<?php

declare(strict_types=1);

namespace src\Content\Page\Block\Types;

use src\Content\Page\Block\AbstractBlockType;

final class ChartBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'chart';
    }

    public function label(): string
    {
        return 'Chart';
    }

    public function description(): string
    {
        return 'Configurable chart block for metrics, comparisons, shares, and editorial data.';
    }

    public function regions(): array
    {
        return ['hero', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Chart';
    }

    public function defaults(): array
    {
        return [
            'title' => 'Useful metrics',
            'subtitle' => 'A clear visual summary.',
            'chartType' => 'metric-bars',
            'datasetLabel' => 'Value',
            'height' => 320,
            'showLegend' => true,
            'showLabels' => true,
            'smoothLine' => true,
            ...$this->surfaceDefaults(),
            'metrics' => [
                ['label' => 'Draft', 'value' => 12, 'color' => '#3b82f6'],
                ['label' => 'Review', 'value' => 7, 'color' => '#f59e0b'],
                ['label' => 'Published', 'value' => 24, 'color' => '#22c55e'],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
            ['name' => 'chartType', 'label' => 'Type', 'type' => 'select', 'options' => [
                ['label' => 'Metric bars', 'value' => 'metric-bars'],
                ['label' => 'Bar', 'value' => 'bar'],
                ['label' => 'Line', 'value' => 'line'],
                ['label' => 'Doughnut', 'value' => 'doughnut'],
                ['label' => 'Pie', 'value' => 'pie'],
            ]],
            ['name' => 'datasetLabel', 'label' => 'Metric group label', 'type' => 'text'],
            ['name' => 'height', 'label' => 'Chart height', 'type' => 'number', 'min' => 220, 'max' => 720, 'step' => 20],
            ['name' => 'showLegend', 'label' => 'Show legend', 'type' => 'checkbox'],
            ['name' => 'showLabels', 'label' => 'Show pie labels', 'type' => 'checkbox'],
            ['name' => 'smoothLine', 'label' => 'Smooth line', 'type' => 'checkbox'],
            $this->surfaceField(),
            ['name' => 'metrics', 'label' => 'Metrics', 'type' => 'metrics'],
        ];
    }
}

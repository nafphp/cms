<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;

final class ChartBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'chart';
    }

    public function label(): string
    {
        return 'Diagram';
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
        return 'Diagram';
    }

    public function defaults(): array
    {
        return [
            'title' => 'Useful metrics',
            'subtitle' => 'A clear visual summary.',
            'chartType' => 'metric-bars',
            'datasetLabel' => 'Value',
            'metricCount' => 3,
            'height' => 320,
            'showLegend' => true,
            'showLabels' => true,
            'smoothLine' => true,
            'label1' => 'Draft',
            'value1' => 12,
            'color1' => '#3b82f6',
            'label2' => 'Review',
            'value2' => 7,
            'color2' => '#f59e0b',
            'label3' => 'Published',
            'value3' => 24,
            'color3' => '#22c55e',
            'label4' => 'Scheduled',
            'value4' => 5,
            'color4' => '#8b5cf6',
            'label5' => 'Archived',
            'value5' => 3,
            'color5' => '#64748b',
            'label6' => 'Declined',
            'value6' => 2,
            'color6' => '#ef4444',
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
            ['name' => 'metricCount', 'label' => 'Number of metrics', 'type' => 'number', 'min' => 1, 'max' => 6, 'step' => 1],
            ['name' => 'height', 'label' => 'Chart height', 'type' => 'number', 'min' => 220, 'max' => 720, 'step' => 20],
            ['name' => 'showLegend', 'label' => 'Show legend', 'type' => 'checkbox'],
            ['name' => 'showLabels', 'label' => 'Show pie labels', 'type' => 'checkbox'],
            ['name' => 'smoothLine', 'label' => 'Smooth line', 'type' => 'checkbox'],
            ['name' => 'label1', 'label' => 'Metric 1 label', 'type' => 'text'],
            ['name' => 'value1', 'label' => 'Metric 1 value', 'type' => 'number', 'step' => 0.1],
            ['name' => 'color1', 'label' => 'Metric 1 color', 'type' => 'color'],
            ['name' => 'label2', 'label' => 'Metric 2 label', 'type' => 'text'],
            ['name' => 'value2', 'label' => 'Metric 2 value', 'type' => 'number', 'step' => 0.1],
            ['name' => 'color2', 'label' => 'Metric 2 color', 'type' => 'color'],
            ['name' => 'label3', 'label' => 'Metric 3 label', 'type' => 'text'],
            ['name' => 'value3', 'label' => 'Metric 3 value', 'type' => 'number', 'step' => 0.1],
            ['name' => 'color3', 'label' => 'Metric 3 color', 'type' => 'color'],
            ['name' => 'label4', 'label' => 'Metric 4 label', 'type' => 'text'],
            ['name' => 'value4', 'label' => 'Metric 4 value', 'type' => 'number', 'step' => 0.1],
            ['name' => 'color4', 'label' => 'Metric 4 color', 'type' => 'color'],
            ['name' => 'label5', 'label' => 'Metric 5 label', 'type' => 'text'],
            ['name' => 'value5', 'label' => 'Metric 5 value', 'type' => 'number', 'step' => 0.1],
            ['name' => 'color5', 'label' => 'Metric 5 color', 'type' => 'color'],
            ['name' => 'label6', 'label' => 'Metric 6 label', 'type' => 'text'],
            ['name' => 'value6', 'label' => 'Metric 6 value', 'type' => 'number', 'step' => 0.1],
            ['name' => 'color6', 'label' => 'Metric 6 color', 'type' => 'color'],
        ];
    }
}

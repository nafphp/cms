<?php

declare(strict_types=1);

namespace NixPHP\CMS\Visualization;

use NixPHP\CMS\Visualization\ChartDataset;
use NixPHP\CMS\Visualization\ChartDefinition;

final class EChartsOptionsFactory
{
    private const COLORS = [
        '#3b82f6',
        '#22c55e',
        '#f59e0b',
        '#ef4444',
        '#8b5cf6',
        '#06b6d4',
        '#f97316',
        '#14b8a6',
    ];

    /**
     * @return array<string, mixed>
     */
    public function make(ChartDefinition $chart): array
    {
        return match ($chart->type) {
            'doughnut', 'pie' => $this->makeDoughnut($chart),
            'line' => $this->makeCartesian($chart, 'line'),
            'metric-bars' => $this->makeMetricBars($chart),
            default => $this->makeCartesian($chart, 'bar'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function makeCartesian(ChartDefinition $chart, string $type): array
    {
        $colors = $this->colors($chart);
        $series = [];
        foreach ($chart->datasets as $index => $dataset) {
            $series[] = [
                'name' => $dataset->label,
                'type' => $type,
                'data' => $dataset->values,
                'itemStyle' => [
                    'color' => $dataset->color ?? $colors[$index % count($colors)],
                    'borderRadius' => $type === 'bar' ? [4, 4, 0, 0] : 0,
                ],
                ...$dataset->options,
            ];
        }

        return $this->mergeOptions([
            'color' => $colors,
            'textStyle' => $this->textStyle(),
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['left' => 0, 'right' => 8, 'top' => 18, 'bottom' => 0, 'containLabel' => true],
            'xAxis' => [
                'type' => 'category',
                'data' => $chart->labels,
                'axisTick' => ['show' => false],
                'axisLine' => ['lineStyle' => ['color' => 'rgba(148, 163, 184, .42)']],
                'axisLabel' => $this->axisLabelStyle(),
            ],
            'yAxis' => [
                'type' => 'value',
                'splitLine' => ['lineStyle' => ['color' => 'rgba(148, 163, 184, .18)']],
                'axisLabel' => $this->axisLabelStyle(),
            ],
            'series' => $series,
        ], $chart->options['echarts'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeDoughnut(ChartDefinition $chart): array
    {
        $colors = $this->colors($chart);
        $dataset = $chart->datasets[0] ?? ChartDataset::make('Value', []);
        $data = [];
        foreach ($chart->labels as $index => $label) {
            $data[] = [
                'name' => $label,
                'value' => $dataset->values[$index] ?? 0,
                'itemStyle' => ['color' => $colors[$index % count($colors)]],
            ];
        }

        return $this->mergeOptions([
            'color' => $colors,
            'textStyle' => $this->textStyle(),
            'tooltip' => ['trigger' => 'item'],
            'legend' => [
                'type' => 'scroll',
                'bottom' => 0,
                'left' => 'center',
                'icon' => 'circle',
                'textStyle' => $this->axisLabelStyle(),
            ],
            'series' => [[
                'name' => $dataset->label,
                'type' => 'pie',
                'radius' => $chart->type === 'pie' ? ['0%', '72%'] : ['48%', '72%'],
                'center' => ['50%', '43%'],
                'avoidLabelOverlap' => true,
                'label' => [
                    'formatter' => '{b}: {c}',
                    ...$this->labelStyle(),
                ],
                'data' => $data,
                ...$dataset->options,
            ]],
        ], $chart->options['echarts'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeMetricBars(ChartDefinition $chart): array
    {
        $colors = $this->colors($chart);
        $dataset = $chart->datasets[0] ?? ChartDataset::make('Value', []);

        $options = [
            'color' => $colors,
            'textStyle' => $this->textStyle(),
            'tooltip' => ['trigger' => 'item'],
            'legend' => ['show' => false, 'selectedMode' => false],
            'grid' => ['left' => 118, 'right' => 34, 'top' => 8, 'bottom' => 4, 'containLabel' => false],
            'xAxis' => [
                'type' => 'value',
                'show' => false,
                'splitLine' => ['show' => false],
            ],
            'yAxis' => [
                'type' => 'category',
                'data' => $chart->labels,
                'axisTick' => ['show' => false],
                'axisLine' => ['show' => false],
                'axisLabel' => [
                    ...$this->axisLabelStyle(13),
                    'align' => 'right',
                    'margin' => 16,
                    'overflow' => 'truncate',
                    'width' => 108,
                ],
            ],
            'series' => [[
                'name' => $dataset->label,
                'type' => 'bar',
                'data' => array_map(
                    static fn(int|float|null $value, int $index): array => [
                        'value' => $value ?? 0,
                        'itemStyle' => ['color' => $colors[$index % count($colors)]],
                    ],
                    $dataset->values,
                    array_keys($dataset->values),
                ),
                'barWidth' => 12,
                'showBackground' => true,
                'backgroundStyle' => [
                    'color' => 'rgba(148, 163, 184, .16)',
                    'borderRadius' => 999,
                ],
                'itemStyle' => ['borderRadius' => 999],
                'emphasis' => [
                    'disabled' => true,
                ],
                'label' => [
                    'show' => true,
                    'position' => 'right',
                    ...$this->labelStyle('rgba(148, 163, 184, .92)'),
                ],
            ]],
        ];

        return $this->mergeOptions($options, $chart->options['echarts'] ?? []);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeOptions(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeOptions($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @return array<int, string>
     */
    private function colors(ChartDefinition $chart): array
    {
        $colors = is_array($chart->options['colors'] ?? null) ? $chart->options['colors'] : [];
        $colors = array_values(array_filter(
            array_map(static fn(mixed $color): string => trim((string) $color), $colors),
            static fn(string $color): bool => preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1,
        ));

        return $colors !== [] ? $colors : self::COLORS;
    }

    /**
     * @return array<string, mixed>
     */
    private function textStyle(): array
    {
        return [
            'fontFamily' => 'Inter, ui-sans-serif, system-ui, sans-serif',
            'textBorderWidth' => 0,
            'textShadowBlur' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function axisLabelStyle(int $fontSize = 12): array
    {
        return [
            'color' => 'rgba(148, 163, 184, .86)',
            'fontSize' => $fontSize,
            'textBorderWidth' => 0,
            'textShadowBlur' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labelStyle(string $color = 'rgba(226, 232, 240, .92)'): array
    {
        return [
            'color' => $color,
            'textBorderWidth' => 0,
            'textShadowBlur' => 0,
        ];
    }
}

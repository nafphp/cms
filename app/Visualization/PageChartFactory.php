<?php

declare(strict_types=1);

namespace NixCMS\Visualization;

use function NixPHP\I18n\t;

final class PageChartFactory
{
    private const MAX_METRICS = 6;

    /**
     * @param array<string, mixed> $props
     */
    public function fromBlockProps(array $props, string $idPrefix = 'page-chart'): ChartDefinition
    {
        $type = $this->chartType((string) ($props['chartType'] ?? 'bar'));
        $values = $this->values($props);
        $options = [
            'datasetLabel' => t((string) ($props['datasetLabel'] ?? 'Value')),
            'height' => $this->height($props['height'] ?? 320),
            'colors' => $this->colors($props),
            'echarts' => [
                'legend' => [
                    'show' => filter_var($props['showLegend'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ],
            ],
        ];

        if (in_array($type, ['doughnut', 'pie'], true)) {
            $options['echarts']['series'] = [[
                'label' => [
                    'show' => filter_var($props['showLabels'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ],
            ]];

            $chart = ChartDefinition::doughnut(
                $this->id($idPrefix, $props),
                $values,
                $this->optionalString($props['title'] ?? null, true),
                $this->optionalString($props['subtitle'] ?? null, true),
                $options,
            );

            if ($type === 'pie') {
                return ChartDefinition::make(
                    $chart->id,
                    'pie',
                    $chart->labels,
                    $chart->datasets,
                    $chart->title,
                    $chart->subtitle,
                    $chart->height,
                    $chart->options,
                );
            }

            return $chart;
        }

        $chart = ChartDefinition::bar(
            $this->id($idPrefix, $props),
            $values,
            $this->optionalString($props['title'] ?? null, true),
            $this->optionalString($props['subtitle'] ?? null, true),
            $options,
        );

        if ($type === 'metric-bars') {
            return ChartDefinition::make(
                $chart->id,
                'metric-bars',
                $chart->labels,
                $chart->datasets,
                $chart->title,
                $chart->subtitle,
                $chart->height,
                $chart->options,
            );
        }

        if ($type === 'line') {
            return ChartDefinition::make(
                $chart->id,
                'line',
                $chart->labels,
                $chart->datasets,
                $chart->title,
                $chart->subtitle,
                $chart->height,
                [
                    ...$chart->options,
                    'echarts' => [
                        ...($chart->options['echarts'] ?? []),
                        'series' => [[
                            'smooth' => filter_var($props['smoothLine'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'symbolSize' => 7,
                            'lineStyle' => ['width' => 3],
                        ]],
                    ],
                ],
            );
        }

        return $chart;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, int|float>
     */
    private function values(array $props): array
    {
        $count = max(1, min(self::MAX_METRICS, (int) ($props['metricCount'] ?? 3)));
        $values = [];

        for ($index = 1; $index <= $count; $index++) {
            $label = trim(t((string) ($props['label' . $index] ?? 'Metric ' . $index)));
            if ($label === '') {
                $label = 'Metric ' . $index;
            }

            $values[$label] = (float) ($props['value' . $index] ?? 0);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<int, string>
     */
    private function colors(array $props): array
    {
        $colors = [];
        for ($index = 1; $index <= self::MAX_METRICS; $index++) {
            $color = trim((string) ($props['color' . $index] ?? ''));
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
                $colors[] = $color;
            }
        }

        return $colors;
    }

    private function chartType(string $type): string
    {
        return in_array($type, ['bar', 'line', 'metric-bars', 'doughnut', 'pie'], true) ? $type : 'bar';
    }

    private function height(mixed $value): int
    {
        return max(220, min(720, (int) $value));
    }

    /**
     * @param array<string, mixed> $props
     */
    private function id(string $prefix, array $props): string
    {
        return $prefix . '-' . substr(hash('sha256', json_encode($props, JSON_THROW_ON_ERROR)), 0, 10);
    }

    private function optionalString(mixed $value, bool $translate = false): ?string
    {
        $value = trim((string) $value);
        if ($translate && $value !== '') {
            $value = trim(t($value));
        }

        return $value === '' ? null : $value;
    }
}

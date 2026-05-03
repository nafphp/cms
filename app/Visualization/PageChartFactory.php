<?php

declare(strict_types=1);

namespace NixCMS\Visualization;

final class PageChartFactory
{
    private const MAX_METRICS = 24;

    /**
     * @param array<string, mixed> $props
     */
    public function fromBlockProps(array $props, string $idPrefix = 'page-chart'): ChartDefinition
    {
        $type = $this->chartType((string) ($props['chartType'] ?? 'bar'));
        $values = $this->values($props);
        $height = $this->height($props['height'] ?? 320);
        if ($type === 'metric-bars') {
            $height = $this->metricBarsHeight($height, count($values));
        }

        $options = [
            'datasetLabel' => $this->translate((string) ($props['datasetLabel'] ?? 'Value')),
            'height' => $height,
            'colors' => $this->colors($props),
            'echarts' => [
                'legend' => [
                    'show' => $type !== 'metric-bars'
                        && filter_var($props['showLegend'] ?? true, FILTER_VALIDATE_BOOLEAN),
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
        $values = [];
        foreach ($this->metrics($props) as $index => $metric) {
            $label = trim($this->translate((string) ($metric['label'] ?? 'Metric ' . ($index + 1))));
            if ($label === '') {
                $label = 'Metric ' . ($index + 1);
            }

            $key = $label;
            $suffix = 2;
            while (array_key_exists($key, $values)) {
                $key = $label . ' ' . $suffix;
                $suffix++;
            }

            $values[$key] = (float) ($metric['value'] ?? 0);
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
        foreach ($this->metrics($props) as $metric) {
            $color = trim((string) ($metric['color'] ?? ''));
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
                $colors[] = $color;
            }
        }

        return $colors;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<int, array{label: string, value: int|float, color: string}>
     */
    private function metrics(array $props): array
    {
        $metrics = [];
        if (is_array($props['metrics'] ?? null)) {
            foreach ($props['metrics'] as $metric) {
                if (!is_array($metric)) {
                    continue;
                }

                $metrics[] = [
                    'label' => (string) ($metric['label'] ?? ''),
                    'value' => is_numeric($metric['value'] ?? null) ? (float) $metric['value'] : 0,
                    'color' => (string) ($metric['color'] ?? ''),
                ];

                if (count($metrics) >= self::MAX_METRICS) {
                    break;
                }
            }
        }

        if ($metrics !== []) {
            return $metrics;
        }

        $highestLegacyMetric = 0;
        for ($index = 1; $index <= 6; $index++) {
            if (array_key_exists('label' . $index, $props) || array_key_exists('value' . $index, $props) || array_key_exists('color' . $index, $props)) {
                $highestLegacyMetric = $index;
            }
        }

        $count = max(1, min(self::MAX_METRICS, max((int) ($props['metricCount'] ?? 3), $highestLegacyMetric)));
        for ($index = 1; $index <= $count; $index++) {
            $metrics[] = [
                'label' => (string) ($props['label' . $index] ?? 'Metric ' . $index),
                'value' => is_numeric($props['value' . $index] ?? null) ? (float) $props['value' . $index] : 0,
                'color' => (string) ($props['color' . $index] ?? ''),
            ];
        }

        return $metrics;
    }

    private function chartType(string $type): string
    {
        return in_array($type, ['bar', 'line', 'metric-bars', 'doughnut', 'pie'], true) ? $type : 'bar';
    }

    private function height(mixed $value): int
    {
        return max(220, min(720, (int) $value));
    }

    private function metricBarsHeight(int $requestedHeight, int $metricCount): int
    {
        $compactHeight = 72 + max(1, $metricCount) * 28;

        return max(180, min($requestedHeight, $compactHeight));
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
            $value = trim($this->translate($value));
        }

        return $value === '' ? null : $value;
    }

    private function translate(string $value): string
    {
        if (function_exists('\NixPHP\I18n\t')) {
            return \NixPHP\I18n\t($value);
        }

        if (function_exists('\t')) {
            return \t($value);
        }

        return $value;
    }
}

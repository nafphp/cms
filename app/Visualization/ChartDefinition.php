<?php

declare(strict_types=1);

namespace NixCMS\Visualization;

final readonly class ChartDefinition
{
    /**
     * @param array<int, string> $labels
     * @param array<int, ChartDataset> $datasets
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $labels,
        public array $datasets,
        public ?string $title = null,
        public ?string $subtitle = null,
        public int $height = 320,
        public array $options = [],
        public string $emptyText = 'No data available',
    ) {
    }

    /**
     * @param array<int, string> $labels
     * @param array<int, ChartDataset> $datasets
     * @param array<string, mixed> $options
     */
    public static function make(
        string $id,
        string $type,
        array $labels,
        array $datasets,
        ?string $title = null,
        ?string $subtitle = null,
        int $height = 320,
        array $options = [],
    ): self {
        return new self(
            self::normalizeId($id),
            $type,
            array_values(array_map(static fn(mixed $label): string => (string) $label, $labels)),
            array_values($datasets),
            $title,
            $subtitle,
            max(180, $height),
            $options,
        );
    }

    /**
     * @param array<string, int|float> $values
     * @param array<string, mixed> $options
     */
    public static function doughnut(
        string $id,
        array $values,
        ?string $title = null,
        ?string $subtitle = null,
        array $options = [],
    ): self {
        return self::make(
            $id,
            'doughnut',
            array_keys($values),
            [ChartDataset::make((string) ($options['datasetLabel'] ?? 'Value'), array_values($values))],
            $title,
            $subtitle,
            (int) ($options['height'] ?? 320),
            $options,
        );
    }

    /**
     * @param array<string, int|float> $values
     * @param array<string, mixed> $options
     */
    public static function bar(
        string $id,
        array $values,
        ?string $title = null,
        ?string $subtitle = null,
        array $options = [],
    ): self {
        return self::make(
            $id,
            'bar',
            array_keys($values),
            [ChartDataset::make((string) ($options['datasetLabel'] ?? 'Value'), array_values($values))],
            $title,
            $subtitle,
            (int) ($options['height'] ?? 320),
            $options,
        );
    }

    public function isEmpty(): bool
    {
        foreach ($this->datasets as $dataset) {
            foreach ($dataset->values as $value) {
                if ((float) ($value ?? 0) !== 0.0) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function normalizeId(string $id): string
    {
        $normalized = strtolower(trim($id));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?: 'chart';

        return trim($normalized, '-') ?: 'chart';
    }
}

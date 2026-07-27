<?php

declare(strict_types=1);

namespace src\Visualization;

final readonly class ChartDataset
{
    /**
     * @param array<int, int|float|null> $values
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $label,
        public array $values,
        public ?string $color = null,
        public array $options = [],
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function make(string $label, array $values, ?string $color = null, array $options = []): self
    {
        return new self($label, array_values($values), $color, $options);
    }
}

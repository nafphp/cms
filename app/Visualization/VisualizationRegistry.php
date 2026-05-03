<?php

declare(strict_types=1);

namespace NixCMS\Visualization;

final class VisualizationRegistry
{
    private ChartRenderer $renderer;

    /**
     * @var array<string, ChartDefinition>
     */
    private array $charts = [];

    public function __construct(?ChartRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new ChartRenderer();
    }

    public function add(ChartDefinition $chart, bool $replace = false): self
    {
        if (!$replace && isset($this->charts[$chart->id])) {
            throw new \InvalidArgumentException(sprintf('Chart "%s" is already registered.', $chart->id));
        }

        $this->charts[$chart->id] = $chart;
        return $this;
    }

    public function find(string $id): ?ChartDefinition
    {
        return $this->charts[$id] ?? null;
    }

    public function render(ChartDefinition|string $chart): string
    {
        if (is_string($chart)) {
            $chart = $this->find($chart) ?? throw new \InvalidArgumentException(sprintf('Chart "%s" is not registered.', $chart));
        }

        return $this->renderer->render($chart);
    }
}

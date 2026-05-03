<?php

declare(strict_types=1);

namespace NixCMS\Visualization;

final class ChartRenderer
{
    public function __construct(
        private readonly EChartsOptionsFactory $optionsFactory = new EChartsOptionsFactory(),
    ) {
    }

    public function render(ChartDefinition $chart): string
    {
        $payload = [
            'id' => $chart->id,
            'type' => $chart->type,
            'title' => $chart->title,
            'subtitle' => $chart->subtitle,
            'height' => $chart->height,
            'empty' => $chart->isEmpty(),
            'emptyText' => $chart->emptyText,
            'option' => $this->optionsFactory->make($chart),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $title = $this->escape((string) ($chart->title ?? ''));
        $subtitle = $this->escape((string) ($chart->subtitle ?? ''));
        $emptyText = $this->escape($chart->emptyText);

        return <<<HTML
<figure class="nixcms-chart" data-nixcms-chart="{$this->escape($chart->id)}">
    <div class="nixcms-chart__header">
        <div>
            {$this->optionalHeading($title)}
            {$this->optionalSubtitle($subtitle)}
        </div>
    </div>
    <div class="nixcms-chart__surface" style="min-height: {$chart->height}px;">
        <div class="nixcms-chart__canvas" style="height: {$chart->height}px;" role="img" aria-label="{$title}"></div>
        <p class="nixcms-chart__empty">{$emptyText}</p>
    </div>
    <script type="application/json" data-nixcms-chart-options>{$json}</script>
</figure>
HTML;
    }

    private function optionalHeading(string $title): string
    {
        return $title !== '' ? '<h3 class="nixcms-chart__title">' . $title . '</h3>' : '';
    }

    private function optionalSubtitle(string $subtitle): string
    {
        return $subtitle !== '' ? '<p class="nixcms-chart__subtitle">' . $subtitle . '</p>' : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

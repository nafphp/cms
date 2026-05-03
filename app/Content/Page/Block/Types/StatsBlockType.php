<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;

final class StatsBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'stats';
    }

    public function label(): string
    {
        return 'Stats';
    }

    public function description(): string
    {
        return 'Three compact metrics for proof points and summaries.';
    }

    public function regions(): array
    {
        return ['main', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Stats';
    }

    public function defaults(): array
    {
        return [
            'valueA' => '12',
            'labelA' => 'Items',
            'valueB' => '98%',
            'labelB' => 'Quality',
            'valueC' => '24/7',
            'labelC' => 'Access',
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'valueA', 'label' => 'Metric A value', 'type' => 'text'],
            ['name' => 'labelA', 'label' => 'Metric A label', 'type' => 'text'],
            ['name' => 'valueB', 'label' => 'Metric B value', 'type' => 'text'],
            ['name' => 'labelB', 'label' => 'Metric B label', 'type' => 'text'],
            ['name' => 'valueC', 'label' => 'Metric C value', 'type' => 'text'],
            ['name' => 'labelC', 'label' => 'Metric C label', 'type' => 'text'],
            $this->surfaceField(),
        ];
    }
}

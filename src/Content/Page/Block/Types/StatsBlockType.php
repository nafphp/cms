<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

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
        return 'Compact statistic cards for proof points and summaries.';
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
            'stats' => [
                ['value' => '12', 'label' => 'Items'],
                ['value' => '98%', 'label' => 'Quality'],
                ['value' => '24/7', 'label' => 'Access'],
            ],
            ...$this->surfaceDefaults('card'),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'stats', 'label' => 'Statistics', 'type' => 'stats'],
            $this->surfaceField(),
        ];
    }
}

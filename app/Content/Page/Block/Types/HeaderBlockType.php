<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;
use NixCMS\Content\Page\Block\PageBuilderBlockOptions;

final class HeaderBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'header';
    }

    public function label(): string
    {
        return 'Header';
    }

    public function description(): string
    {
        return 'Site header with logo text and a selected navigation tree.';
    }

    public function regions(): array
    {
        return ['header'];
    }

    public function previewTitle(): string
    {
        return 'Site header';
    }

    public function defaults(): array
    {
        return [
            'logoText' => '',
            'logoImage' => '',
            'navigationIdentifier' => '',
            'presentation' => 'horizontal',
            'showLogoMark' => false,
            'showDivider' => false,
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'logoText', 'label' => 'Logo text', 'type' => 'text'],
            ['name' => 'logoImage', 'label' => 'Logo image', 'type' => 'media', 'altTarget' => 'logoText'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => [
                ['label' => 'No navigation', 'value' => ''],
                ...PageBuilderBlockOptions::navigationOptions(),
            ]],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['horizontal', 'compact']],
            ['name' => 'showLogoMark', 'label' => 'Logo mark', 'type' => 'checkbox'],
            ['name' => 'showDivider', 'label' => 'Header divider', 'type' => 'checkbox'],
        ];
    }
}

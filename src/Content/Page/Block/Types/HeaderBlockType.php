<?php

declare(strict_types=1);

namespace src\Content\Page\Block\Types;

use src\Content\Page\Block\AbstractBlockType;
use src\Content\Page\Block\PageBuilderBlockOptions;

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

    public function unique(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        return [
            'logoText' => '',
            'logoImage' => '',
            'navigationIdentifier' => '',
            'presentation' => 'horizontal',
            'linkStyle' => 'blank',
            'linkTheme' => 'default',
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
            ['name' => 'linkStyle', 'label' => 'Link style', 'type' => 'select', 'options' => [
                ['label' => 'Blank', 'value' => 'blank'],
                ['label' => 'Underline', 'value' => 'underline'],
                ['label' => 'Pill', 'value' => 'pill'],
                ['label' => 'Rounded pill', 'value' => 'rounded-pill'],
                ['label' => 'Filled', 'value' => 'filled'],
                ['label' => 'Filled rounded', 'value' => 'filled-rounded'],
            ]],
            ['name' => 'linkTheme', 'label' => 'Link theme', 'type' => 'select', 'options' => [
                ['label' => 'Default', 'value' => 'default'],
                ['label' => 'Accent', 'value' => 'accent'],
                ['label' => 'Surface', 'value' => 'surface'],
            ]],
            ['name' => 'showLogoMark', 'label' => 'Logo mark', 'type' => 'checkbox'],
            ['name' => 'showDivider', 'label' => 'Header divider', 'type' => 'checkbox'],
        ];
    }
}

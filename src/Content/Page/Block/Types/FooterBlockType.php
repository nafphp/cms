<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Content\Page\Block\PageBuilderBlockOptions;

final class FooterBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'footer';
    }

    public function label(): string
    {
        return 'Footer';
    }

    public function description(): string
    {
        return 'Site footer with optional navigation and small imprint text.';
    }

    public function regions(): array
    {
        return ['footer'];
    }

    public function previewTitle(): string
    {
        return 'Site footer';
    }

    public function unique(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        $navigationOptions = PageBuilderBlockOptions::navigationOptions();

        return [
            'text'                 => 'Powered by NafCMS',
            'navigationIdentifier' => (string) ($navigationOptions[0]['value'] ?? 'main'),
            'presentation'         => 'compact',
            'linkStyle'            => 'blank',
            'linkTheme'            => 'default',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'text', 'label' => 'Footer text', 'type' => 'text'],
            ['name' => 'navigationIdentifier', 'label' => 'Navigation', 'type' => 'select', 'options' => PageBuilderBlockOptions::navigationOptions()],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['compact', 'horizontal']],
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
        ];
    }
}

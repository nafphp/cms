<?php

declare(strict_types=1);

namespace NixCMS\Examples\BlockType;

use NixCMS\Content\Page\Block\AbstractBlockType;

final class ExampleQuoteBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'example_quote';
    }

    public function label(): string
    {
        return 'Example quote';
    }

    public function description(): string
    {
        return 'Plugin-provided quote block for page-builder demos.';
    }

    public function regions(): array
    {
        return ['main', 'aside'];
    }

    public function defaults(): array
    {
        return [
            'quote' => 'A concise customer quote belongs here.',
            'source' => '',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea'],
            ['name' => 'source', 'label' => 'Source', 'type' => 'text'],
        ];
    }
}

\cms()->blockTypes()->add(new ExampleQuoteBlockType(), replace: true);

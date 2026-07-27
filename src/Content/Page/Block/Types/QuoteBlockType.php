<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class QuoteBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'quote';
    }

    public function label(): string
    {
        return 'Quote';
    }

    public function description(): string
    {
        return 'Pull quote, testimonial, or editorial statement.';
    }

    public function regions(): array
    {
        return ['main', 'aside'];
    }

    public function previewTitle(): string
    {
        return 'Quote';
    }

    public function defaults(): array
    {
        return [
            'quote' => 'A focused quote or testimonial belongs here.',
            'source' => '',
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea'],
            ['name' => 'source', 'label' => 'Source', 'type' => 'text'],
            $this->surfaceField(),
        ];
    }
}

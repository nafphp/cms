<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class ArticleTitleBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'article-title';
    }

    public function label(): string
    {
        return 'Article title';
    }

    public function description(): string
    {
        return 'Render the title and optional teaser from the resolved article.';
    }

    public function regions(): array
    {
        return ['hero', 'header', 'main'];
    }

    public function previewTitle(): string
    {
        return 'Resolved article title';
    }

    public function defaults(): array
    {
        return [
            'showTeaser' => true,
            'level' => 'h1',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'showTeaser', 'label' => 'Show teaser', 'type' => 'checkbox'],
            ['name' => 'level', 'label' => 'Heading level', 'type' => 'select', 'options' => ['h1', 'h2', 'h3']],
        ];
    }
}

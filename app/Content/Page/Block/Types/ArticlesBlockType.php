<?php

declare(strict_types=1);

namespace NixCMS\Content\Page\Block\Types;

use NixCMS\Content\Page\Block\AbstractBlockType;

final class ArticlesBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'articles';
    }

    public function label(): string
    {
        return 'Article collection';
    }

    public function description(): string
    {
        return 'Render a selected article query as list, cards, or compact links.';
    }

    public function regions(): array
    {
        return ['main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Latest articles';
    }

    public function defaults(): array
    {
        return [
            'source' => 'latest',
            'limit' => 5,
            'presentation' => 'list',
            'language' => 'current',
            'detailPath' => '/articles/{slug}',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'source', 'label' => 'Source', 'type' => 'select', 'options' => ['latest', 'featured', 'category', 'related']],
            ['name' => 'limit', 'label' => 'Limit', 'type' => 'number'],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['list', 'cards', 'compact']],
            ['name' => 'language', 'label' => 'Language', 'type' => 'select', 'options' => ['current', 'de', 'en', 'fr', 'es', 'it']],
            ['name' => 'detailPath', 'label' => 'Detail URL pattern', 'type' => 'text'],
        ];
    }
}

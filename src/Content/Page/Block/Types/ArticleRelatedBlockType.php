<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class ArticleRelatedBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'article-related';
    }

    public function label(): string
    {
        return 'Related articles';
    }

    public function description(): string
    {
        return 'Render published articles related to the resolved article.';
    }

    public function regions(): array
    {
        return ['hero', 'main', 'aside', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Related articles';
    }

    public function defaults(): array
    {
        return [
            'title' => 'Related articles',
            'source' => 'category',
            'limit' => 3,
            'presentation' => 'cards',
            'detailPath' => '/articles/{slug}',
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['name' => 'source', 'label' => 'Source', 'type' => 'select', 'options' => ['category', 'latest']],
            ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'min' => 1, 'max' => 12],
            ['name' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['list', 'cards', 'compact']],
            ['name' => 'detailPath', 'label' => 'Detail URL pattern', 'type' => 'text'],
        ];
    }
}

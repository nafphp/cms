<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block\Types;

use Naf\CMS\Content\Page\Block\AbstractBlockType;

final class ArticleMetaBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'article-meta';
    }

    public function label(): string
    {
        return 'Article meta';
    }

    public function description(): string
    {
        return 'Render article language, status, publish date, author, and tags.';
    }

    public function regions(): array
    {
        return ['hero', 'header', 'aside', 'main', 'footer'];
    }

    public function previewTitle(): string
    {
        return 'Article metadata';
    }

    public function defaults(): array
    {
        return [
            'showStatus' => false,
            'showAuthor' => true,
            'showTags' => true,
            ...$this->surfaceDefaults(),
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'showStatus', 'label' => 'Show status', 'type' => 'checkbox'],
            ['name' => 'showAuthor', 'label' => 'Show author', 'type' => 'checkbox'],
            ['name' => 'showTags', 'label' => 'Show tags', 'type' => 'checkbox'],
            $this->surfaceField(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Page\Block\Types;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;

final class ArticleContentBlockType extends AbstractBlockType
{
    public function id(): string
    {
        return 'article-content';
    }

    public function label(): string
    {
        return 'Article content';
    }

    public function description(): string
    {
        return 'Render the Editor.js body of the resolved article.';
    }

    public function regions(): array
    {
        return ['hero', 'main'];
    }

    public function previewTitle(): string
    {
        return 'Resolved article content';
    }
}

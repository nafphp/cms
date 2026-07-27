<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article\Block;

use NixPHP\CMS\Content\Article\Block\AbstractBlock;

final class ParagraphBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>';
    }
}

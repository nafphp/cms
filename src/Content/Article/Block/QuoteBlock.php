<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article\Block;

use NixPHP\CMS\Content\Article\Block\AbstractBlock;

final class QuoteBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        $caption = trim((string) ($data['caption'] ?? ''));

        return '<blockquote><p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>'
            . ($caption !== '' ? '<cite>' . $this->escape($caption) . '</cite>' : '')
            . '</blockquote>';
    }
}

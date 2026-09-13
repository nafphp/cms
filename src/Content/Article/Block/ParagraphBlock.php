<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Article\Block;

use Naf\CMS\Content\Article\Block\AbstractBlock;

final class ParagraphBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>';
    }
}

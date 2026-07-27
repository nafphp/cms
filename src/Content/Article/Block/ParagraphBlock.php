<?php

declare(strict_types=1);

namespace src\Content\Article\Block;

use src\Content\Article\Block\AbstractBlock;

final class ParagraphBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>';
    }
}

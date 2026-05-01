<?php

declare(strict_types=1);

namespace NixCMS\Content\Article\Block;

final class ParagraphBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<p>' . $this->cleanInline((string) ($data['text'] ?? '')) . '</p>';
    }
}

<?php

declare(strict_types=1);

namespace NixCMS\Content\Article\Block;

final class HeaderBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        $level = max(2, min(4, (int) ($data['level'] ?? 2)));
        return sprintf('<h%d>%s</h%d>', $level, $this->cleanInline((string) ($data['text'] ?? '')), $level);
    }
}

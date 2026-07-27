<?php

declare(strict_types=1);

namespace src\Content\Article\Block;

use src\Content\Article\Block\AbstractBlock;

final class CodeBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<pre><code>' . $this->escape((string) ($data['code'] ?? '')) . '</code></pre>';
    }
}

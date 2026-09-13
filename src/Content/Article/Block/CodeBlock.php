<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Article\Block;

use Naf\CMS\Content\Article\Block\AbstractBlock;

final class CodeBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<pre><code>' . $this->escape((string) ($data['code'] ?? '')) . '</code></pre>';
    }
}

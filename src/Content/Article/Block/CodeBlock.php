<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Article\Block;

use NixPHP\CMS\Content\Article\Block\AbstractBlock;

final class CodeBlock extends AbstractBlock
{
    public function render(array $data): string
    {
        return '<pre><code>' . $this->escape((string) ($data['code'] ?? '')) . '</code></pre>';
    }
}
